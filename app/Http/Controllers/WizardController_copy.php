<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Auth;
use App\Models\ComplianceStandard;
use App\Models\MetadataKey;
use App\Models\DataSourceRef;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Country;
use App\Models\BusinessProfile; 



class WizardController extends Controller
{
    private $high_risk = [];
    private $medium_risk = [];

    private function defaultClassificationLevels(): array
    {
        return [
            1 => ['id' => 1, 'name' => 'Public',                 'example' => 'Press releases',                'access' => 'None/minimal'],
            2 => ['id' => 2, 'name' => 'Internal/Proprietary',   'example' => 'Internal memos',                'access' => 'Moderate'],
            3 => ['id' => 3, 'name' => 'Confidential/Sensitive', 'example' => 'Client lists, some HR records', 'access' => 'Strong'],
            4 => ['id' => 4, 'name' => 'Restricted',             'example' => 'PII, PHI, trade secrets',       'access' => 'Very strict'],
        ];
    }

    private function getBusinessContext(): array
    {
        $user = Auth::user();
        $bizId = $user?->business_id;

        $profile = $bizId
            ? BusinessProfile::where('business_id', $bizId)->first()
            : null;

        $country      = $profile->country ?? $user->default_country ?? null;
        $industry     = $profile->industry ?? $user->supplier_type ?? null;
        $aboutCompany = $profile->about_company ?? null;

        return [
            'bizId'        => $bizId,
            'profile'      => $profile,
            'country'      => $country,
            'industry'     => $industry,
            'aboutCompany' => $aboutCompany,
        ];
    }

    private function fetchLLMRegulations(string $country, string $industry, ?string $aboutCompany = null): array
    {
        $companyContext = $aboutCompany ? "Company context: {$aboutCompany}\n" : '';
        $prompt = <<<EOD
As a world-renowned expert in privacy and data regulation, for the country "{$country}" and the industry/sector "{$industry}":
{$companyContext}
List major privacy, industry specific and data regulations, laws, and standards that apply to organizations in that context, avoid duplications across states and country level regulations.
If there is overlap between state and federal regulations only return federal regulatory requirement.
Include other notable regulations that are likely to apply based on the industry and relationships (e.g., international collaborations).
Return as a pure JSON array where each object has these properties only:
- "standard": Standard or regulation name.
- "jurisdiction": Jurisdiction/region.
- "fields": List of regulated data fields as a JSON array of field names.
EOD;

        try {
            $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a world expert in global data regulation and compliance. Always answer precisely and output only the requested format.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens'  => 2500,
            ]);

            if ($response->successful() && isset($response['choices'][0]['message']['content'])) {
                return ['content' => $response['choices'][0]['message']['content'], 'error' => null];
            }
            $msg = is_string($response->body()) ? $response->body() : 'No valid response';
            return ['content' => null, 'error' => 'OpenAI API error: ' . $msg];
        } catch (\Exception $ex) {
            return ['content' => null, 'error' => 'OpenAI Error: ' . $ex->getMessage()];
        }
    }

    public function essentialSetup(Request $request)
      {
      $ctx = $this->getBusinessContext();
      $user = Auth::user();
      if (!$user) abort(403, 'Not authenticated');

      $countries = Country::orderBy('name')->pluck('name', 'name');

      // Section 1
      $section1Complete = $ctx['profile'] && $ctx['profile']->industry && $ctx['profile']->country;

      // Section 2
      $levels = $this->defaultClassificationLevels();
      $dcPreferredTags = [];
      if ($ctx['profile'] && $ctx['profile']->data_classification) {
          $dc = is_string($ctx['profile']->data_classification) ? json_decode($ctx['profile']->data_classification, true) : $ctx['profile']->data_classification;
          if (is_array($dc) && isset($dc['levels'])) {
              foreach ($dc['levels'] as $lvl) {
                  if (isset($lvl['id'])) $dcPreferredTags[$lvl['id']] = $lvl['preferred_tag'] ?? '';
              }
          }
      }
      $section2Complete = ($ctx['profile'] && !empty($ctx['profile']->data_classification));

      $activeSection = (int) $request->query('section', 1);
      if ($activeSection < 1 || $activeSection > 4) $activeSection = 1;

      // Section 3 (saved regs + status)
      $savedRegulationsRaw = null;
      if ($ctx['profile']) {
          $savedRegulationsRaw = $ctx['profile']->selected_regulations ?? $ctx['profile']->selecte_regulations ?? null;
      }
      $section3Complete = false;
      if ($savedRegulationsRaw) {
          $decoded = is_string($savedRegulationsRaw) ? json_decode($savedRegulationsRaw, true) : (is_array($savedRegulationsRaw) ? $savedRegulationsRaw : null);
          if (is_array($decoded) && count($decoded) > 0) $section3Complete = true;
      }
      $savedRegulations = is_array($savedRegulationsRaw) ? json_encode($savedRegulationsRaw) : $savedRegulationsRaw;
      $sessionRecommendation = session('wizard.regulations_recommendation');
      $sessionError          = session('wizard.regulations_error');

      // Section 4 (subject categories) saved + status
      $savedSubjectRaw = null;
      if ($ctx['profile']) {
          $savedSubjectRaw = $ctx['profile']->selected_subject_category ?? null;
      }
      $section4Complete = false;
      if ($savedSubjectRaw) {
          $decoded = is_string($savedSubjectRaw) ? json_decode($savedSubjectRaw, true) : (is_array($savedSubjectRaw) ? $savedSubjectRaw : null);
          if (is_array($decoded) && count($decoded) > 0) $section4Complete = true;
      }
      $savedSubjectCategories = is_array($savedSubjectRaw) ? json_encode($savedSubjectRaw) : $savedSubjectRaw;
      $sessionSubjRecommendation = session('wizard.subjects_recommendation');
      $sessionSubjError          = session('wizard.subjects_error');

      return view('wizard.essential_setup', [
          'bizId'               => $ctx['bizId'],
          'profile'             => $ctx['profile'],
          'countries'           => $countries,
          'activeSection'       => $activeSection,
          'sectionStatuses'     => [
              's1' => $section1Complete,
              's2' => $section2Complete,
              's3' => $section3Complete,
              's4' => $section4Complete,
          ],
          // Section 2
          'dcLevels'            => array_values($levels),
          'dcPreferredTags'     => $dcPreferredTags,
          // Section 3
          'country'             => $ctx['country'],
          'industry'            => $ctx['industry'],
          'about_company'       => $ctx['aboutCompany'],
          'savedRegulations'    => $savedRegulations,
          'recommendationRaw'   => $sessionRecommendation,
          'regulationsError'    => $sessionError,
          // Section 4
          'savedSubjectCategories' => $savedSubjectCategories,
          'subjectsRecommendationRaw' => $sessionSubjRecommendation,
          'subjectsError'            => $sessionSubjError,
      ]);
      }

  
  	private function fetchLLMSubjectCategories(string $country, string $industry, ?string $aboutCompany = null): array
      {
      $companyContext = $aboutCompany ? "Company context: {$aboutCompany}\n" : '';
      $prompt = <<<EOD
      For the country "{$country}" and the industry/sector "{$industry}":
      {$companyContext}
      List the likely data subject categories and a brief description for each. Output only a JSON array of objects with exactly these keys:

      "category": short category name (e.g., Customers, Employees, Students, Financial Records, Health Information)
      "description": a brief sentence describing the category
      Example:
      [
      { "category": "Customers", "description": "Information about individuals who purchase or use your products/services." },
      { "category": "Employees", "description": "Information about staff and HR records." }
      ]
      EOD;

      try {
          $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
          $response = \Illuminate\Support\Facades\Http::withHeaders([
              'Authorization' => 'Bearer ' . $apiKey,
              'Content-Type'  => 'application/json',
          ])->post('https://api.openai.com/v1/chat/completions', [
              'model' => 'gpt-4.1',
              'messages' => [
                  ['role' => 'system', 'content' => 'You provide ONLY the requested JSON array without extra text.'],
                  ['role' => 'user',   'content' => $prompt],
              ],
              'temperature' => 0.2,
              'max_tokens'  => 1800,
          ]);

          if ($response->successful() && isset($response['choices'][0]['message']['content'])) {
              return ['content' => $response['choices'][0]['message']['content'], 'error' => null];
          }
          $msg = is_string($response->body()) ? $response->body() : 'No valid response';
          return ['content' => null, 'error' => 'OpenAI API error: ' . $msg];
      } catch (\Exception $ex) {
          return ['content' => null, 'error' => 'OpenAI Error: ' . $ex->getMessage()];
      }
      }
  
  
    public function essentialSetupSection1Save(Request $request)
    {
    $user = Auth::user();
    if (!$user) abort(403, 'Not authenticated');

    $bizId = $user->business_id;
    if (!$bizId) return back()->withErrors('Your account has no business_id. Please contact support.');

    $validated = $request->validate([
        'industry'      => ['required', 'string', 'max:255'],
        'country'       => ['required', 'string', 'max:255'],
        'about_company' => ['nullable', 'string', 'max:5000'],
    ]);

    $profile = BusinessProfile::firstOrNew(['business_id' => $bizId]);
    $profile->industry       = $validated['industry'];
    $profile->country        = $validated['country'];
    $profile->about_company  = $validated['about_company'] ?? null;

    // Only set audit columns if they exist in your DB
    if (!$profile->exists && \Schema::hasColumn('business_profile', 'created_by')) {
        $profile->created_by = $user->id;
    }
    if (\Schema::hasColumn('business_profile', 'updated_by')) {
        $profile->updated_by = $user->id;
    }

    $profile->save();

    return redirect()->route('wizard.essentialSetup', ['section' => 1])->with('success', 'Company profile saved successfully.');
    }

   public function essentialSetupSection2Save(Request $request)
    {
    $user = Auth::user();
    if (!$user) abort(403, 'Not authenticated');

    $bizId = $user->business_id;
    if (!$bizId) return back()->withErrors('Your account has no business_id. Please contact support.');

    $levels = $this->defaultClassificationLevels();
    $validated = $request->validate([
        'preferred_tags'   => ['sometimes', 'array'],
        'preferred_tags.*' => ['nullable', 'string', 'max:100'],
    ]);
    $tags = $validated['preferred_tags'] ?? [];

    $data = ['levels' => []];
    foreach ($levels as $id => $level) {
        $tag = trim($tags[$id] ?? '');
        $data['levels'][] = [
            'id'            => $level['id'],
            'standard_name' => $level['name'],
            'example'       => $level['example'],
            'access'        => $level['access'],
            'preferred_tag' => $tag !== '' ? $tag : null,
        ];
    }

    $profile = BusinessProfile::firstOrNew(['business_id' => $bizId]);
    $profile->data_classification = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (!$profile->exists && \Schema::hasColumn('business_profile', 'created_by')) {
        $profile->created_by = $user->id;
    }
    if (\Schema::hasColumn('business_profile', 'updated_by')) {
        $profile->updated_by = $user->id;
    }
    $profile->save();

    return redirect()->route('wizard.essentialSetup', ['section' => 2])->with('success', 'Data classification saved.');
    }

    public function essentialSetupSection3Recommend(Request $request)
    {
        $ctx = $this->getBusinessContext();
        $user = Auth::user();
        if (!$user) abort(403, 'Not authenticated');

        if (!$ctx['bizId']) {
            return back()->withErrors('Your account has no business_id. Please contact support.');
        }
        if (!$ctx['profile'] || empty($ctx['country']) || empty($ctx['industry'])) {
            return redirect()->route('wizard.essentialSetup', ['section' => 1])
                ->withErrors('Please complete Section 1 (Company Profile) first: Country and Industry are required.');
        }

        $res = $this->fetchLLMRegulations($ctx['country'], $ctx['industry'], $ctx['aboutCompany']);
        if ($res['content']) {
            session(['wizard.regulations_recommendation' => $res['content']]);
            session()->forget('wizard.regulations_error');
        } else {
            session()->forget('wizard.regulations_recommendation');
            session(['wizard.regulations_error' => $res['error'] ?? 'LLM failed.']);
        }

        return redirect()->route('wizard.essentialSetup', ['section' => 3]);
    }

    public function essentialSetupSection3Save(Request $request)
    {
    $user = Auth::user();
    if (!$user) abort(403, 'Not authenticated');

    $bizId = $user->business_id;
    if (!$bizId) return back()->withErrors('Your account has no business_id. Please contact support.');

    $json = $request->input('regulations_json', '');
    if (!$json) return back()->withErrors('Please select at least one standard.');

    $arr = json_decode($json, true);
    if (!is_array($arr) || !count($arr)) {
        return back()->withErrors('Invalid selection. Please try again.');
    }

    // Decide which column to use
    $targetColumn = null;
    if (\Schema::hasColumn('business_profile', 'selected_regulations')) {
        $targetColumn = 'selected_regulations';
    } elseif (\Schema::hasColumn('business_profile', 'selecte_regulations')) {
        $targetColumn = 'selecte_regulations';
    } else {
        \Log::error('BusinessProfile table missing selected_regulations column (or legacy selecte_regulations).');
        return back()->withErrors('Server configuration error: selected_regulations column not found.');
    }

    try {
        $profile = BusinessProfile::firstOrNew(['business_id' => $bizId]);
        $profile->{$targetColumn} = json_encode($arr, JSON_UNESCAPED_UNICODE);
        if (!$profile->exists && \Schema::hasColumn('business_profile', 'created_by')) {
            $profile->created_by = $user->id;
        }
        if (\Schema::hasColumn('business_profile', 'updated_by')) {
            $profile->updated_by = $user->id;
        }
        $profile->save();
    } catch (\Throwable $e) {
        \Log::error('[essentialSetupSection3Save] Failed to save regulations', ['err' => $e->getMessage()]);
        return back()->withErrors('Failed to save selected regulations: ' . $e->getMessage());
    }

    session()->forget(['wizard.regulations_recommendation', 'wizard.regulations_error']);

    return redirect()->route('wizard.essentialSetup', ['section' => 3])->with('success', 'Applicable regulations saved.');
    }

  	public function essentialSetupSection4Recommend(Request $request)
      {
      $ctx = $this->getBusinessContext();
      $user = Auth::user();
      if (!$user) abort(403, 'Not authenticated');

      if (!$ctx['bizId']) {
          return back()->withErrors('Your account has no business_id. Please contact support.');
      }
      if (!$ctx['profile'] || empty($ctx['country']) || empty($ctx['industry'])) {
          return redirect()->route('wizard.essentialSetup', ['section' => 1])
              ->withErrors('Please complete Section 1 (Company Profile) first: Country and Industry are required.');
      }

      $res = $this->fetchLLMSubjectCategories($ctx['country'], $ctx['industry'], $ctx['aboutCompany']);
      if ($res['content']) {
          session(['wizard.subjects_recommendation' => $res['content']]);
          session()->forget('wizard.subjects_error');
      } else {
          session()->forget('wizard.subjects_recommendation');
          session(['wizard.subjects_error' => $res['error'] ?? 'LLM failed.']);
      }

      return redirect()->route('wizard.essentialSetup', ['section' => 4]);
      }
  
  	public function essentialSetupSection4Save(Request $request)
    {
    $user = Auth::user();
    if (!$user) abort(403, 'Not authenticated');

    $bizId = $user->business_id;
    if (!$bizId) return back()->withErrors('Your account has no business_id. Please contact support.');

    $json = $request->input('subject_categories_json', '');
    if (!$json) return back()->withErrors('Please select at least one subject category.');

    $arr = json_decode($json, true);
    if (!is_array($arr) || !count($arr)) {
        return back()->withErrors('Invalid selection. Please try again.');
    }

    // Build the likely_data_subject_area string from selected categories
    $categories = [];
    foreach ($arr as $item) {
        if (!is_array($item)) continue;
        $cat = $item['category'] ?? $item['name'] ?? $item['subject'] ?? $item['title'] ?? '';
        $cat = trim((string)$cat);
        if ($cat !== '') $categories[] = mb_strtolower($cat);
    }
    $categories = array_values(array_unique($categories));
    $list = implode(', ', $categories);
    $llmLine = '- "likely_data_subject_area": State data subject area, to use are - ' . $list . "\n";

    try {
        $profile = BusinessProfile::firstOrNew(['business_id' => $bizId]);
        // Save JSON selection
        if (\Schema::hasColumn('business_profile', 'selected_subject_category')) {
            $profile->selected_subject_category = json_encode($arr, JSON_UNESCAPED_UNICODE);
        } else {
            return back()->withErrors('Server configuration error: selected_subject_category column not found.');
        }
        // Save the helper line if column exists
        if (\Schema::hasColumn('business_profile', 'subject_category_for_llm')) {
            $profile->subject_category_for_llm = $llmLine;
        }

        if (!$profile->exists && \Schema::hasColumn('business_profile', 'created_by')) {
            $profile->created_by = $user->id;
        }
        if (\Schema::hasColumn('business_profile', 'updated_by')) {
            $profile->updated_by = $user->id;
        }

        $profile->save();
    } catch (\Throwable $e) {
        \Log::error('[essentialSetupSection4Save] Failed to save subject categories', ['err' => $e->getMessage()]);
        return back()->withErrors('Failed to save subject categories: ' . $e->getMessage());
    }

    // Clear any recommendation sessions
    session()->forget(['wizard.subjects_recommendation', 'wizard.subjects_error']);

    return redirect()->route('wizard.essentialSetup', ['section' => 4])->with('success', 'Subject categories saved.');
    }
  
    // Existing wizard steps and other methods (unchanged)
    private function config()
    {
        $editId = session('wizard.edit_id');
        if ($editId) {
            return auth()->user()->dataConfigs()->findOrFail($editId);
        }
        abort(404, "No data configuration wizard started. Please use '+ Add New Data Source' to begin.");
    }

    public function startWizard(Request $request)
    {
        $newConfig = auth()->user()->dataConfigs()->create([]);
        session(['wizard.edit_id' => $newConfig->id]);
        session()->forget([
            'wizard.data_sources', 'wizard.regulations', 'wizard.metadata',
            'wizard.high_risk_types', 'wizard.medium_risk_types', 'wizard.api_config',
            'wizard.pii_volume_thresholds', 'wizard.pii_volume_category'
        ]);
        return redirect()->route('wizard.step1');
    }

    public function step1()
    {
        $selected = session('wizard.data_sources');
        if (is_null($selected) || $selected === '') {
            $selected = $this->config()->data_sources ?? '';
        }
        if (is_array($selected)) $selected = $selected[0] ?? '';
        $sources = DataSourceRef::select('data_source_name', 'description')->get();
        return view('wizard.step1', ['sources' => $sources, 'selected' => $selected]);
    }

    public function step1Post(Request $request)
    {
        $d = $request->validate(['data_sources'=>'required|string']);
        session(['wizard.data_sources' => $d['data_sources']]);
        $this->saveWizardToConfig(['data_sources' => $d['data_sources']]);
        return redirect()->route('wizard.step2');
    }

    public function step2()
    {
        $config = $this->config();
        $sessionReg = session('wizard.regulations', null);
        $regJson = $sessionReg ?: ($config->regulations ?? null);
        $standards = ComplianceStandard::all();

        $selected = [];
        if ($regJson) {
            $arr = is_string($regJson) ? json_decode($regJson, true) : $regJson;
            if (is_array($arr)) {
                $stdNameToId = $standards->pluck('id', 'standard')->toArray();
                foreach ($arr as $st) {
                    if (isset($st['standard']) && isset($stdNameToId[$st['standard']])) {
                        $selected[] = $stdNameToId[$st['standard']];
                    }
                }
            }
        }
        return view('wizard.step2', [
            'standards'     => $standards,
            'selected'      => $selected,
            'high_risk'     => $this->high_risk,
            'medium_risk'   => $this->medium_risk,
            'config'        => $config,
        ]);
    }

    public function step2Post(Request $request)
    {
        $json = $request->input('regulations_json');
        if (empty($json)) {
            return back()->withErrors('Please select at least one regulation and field.');
        }
        $data = json_decode($json, true);
        if (!$data || !is_array($data) || !count($data)) {
            return back()->withErrors('Fill at least one Regulation + field.');
        }
        session(['wizard.regulations' => $data]);
        $this->saveWizardToConfig(['regulations' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
        return redirect()->route('wizard.step3');
    }

    public function step3()
    {
        $levels = [
            1 => ['id' => 1, 'name' => 'Public', 'example' => 'Press releases', 'access' => 'None/minimal'],
            2 => ['id' => 2, 'name' => 'Internal/Proprietary', 'example' => 'Internal memos', 'access' => 'Moderate'],
            3 => ['id' => 3, 'name' => 'Confidential/Sensitive', 'example' => 'Client lists, some HR records', 'access' => 'Strong'],
            4 => ['id' => 4, 'name' => 'Restricted', 'example' => 'PII, PHI, trade secrets', 'access' => 'Very strict'],
        ];

        $config = $this->config();
        $preferredTags = [];
        $classificationData = $config->data_classification ?? [];
        if (is_string($classificationData)) $classificationData = json_decode($classificationData, true);
        if (is_array($classificationData) && isset($classificationData['levels'])) {
            foreach ($classificationData['levels'] as $lvl) {
                $preferredTags[$lvl['id']] = $lvl['preferred_tag'] ?? '';
            }
        }
        $preferredTags = array_merge($preferredTags, old('preferred_tags', []));
        return view('wizard.step3', [
            'levels' => array_values($levels),
            'preferredTags' => $preferredTags,
        ]);
    }

    public function step3Post(Request $request)
    {
        $levels = [
            1 => ['id' => 1, 'name' => 'Public', 'example' => 'Press releases', 'access' => 'None/minimal'],
            2 => ['id' => 2, 'name' => 'Internal/Proprietary', 'example' => 'Internal memos', 'access' => 'Moderate'],
            3 => ['id' => 3, 'name' => 'Confidential/Sensitive', 'example' => 'Client lists, some HR records', 'access' => 'Strong'],
            4 => ['id' => 4, 'name' => 'Restricted', 'example' => 'PII, PHI, trade secrets', 'access' => 'Very strict'],
        ];
        $validated = $request->validate([
            'preferred_tags'   => ['sometimes', 'array'],
            'preferred_tags.*' => ['nullable', 'string', 'max:100'],
        ]);
        $inputTags = $validated['preferred_tags'] ?? [];
        $dataClassification = [ 'levels' => [] ];
        foreach ($levels as $id => $level) {
            $tag = trim($inputTags[$id] ?? '');
            $dataClassification['levels'][] = [
                'id' => $level['id'],
                'standard_name' => $level['name'],
                'example' => $level['example'],
                'access' => $level['access'],
                'preferred_tag' => $tag !== '' ? $tag : null,
            ];
        }
        $this->saveWizardToConfig(['data_classification' => json_encode($dataClassification)]);
        return redirect()->route('wizard.step5');
    }
  
 	public function privacyRegulations(Request $request)
    {
    $ctx = $this->getBusinessContext();

    // Use business_profile values primarily; fallback already handled in helper
    $country  = $ctx['country'] ?? 'Australia';
    $industry = $ctx['industry'] ?? 'General Sector';

    $data = [
    'result'   => null,
    'error'    => null,
    'country'  => $country,
    'industry' => $industry,
    ];

    if ($request->isMethod('post')) {
    $res = $this->fetchLLMRegulations($country, $industry, $ctx['aboutCompany']);
    if ($res['content']) {
    $data['result'] = $res['content'];
    } else {
    $data['error'] = $res['error'] ?? 'Unknown error';
    }
    }
    return view('wizard.privacy_regulations', $data);
    }

/*public function subjectCategories(Request $request)
{
    $user = \Auth::user();
    $country = $user->default_country ?? 'Australia';
    $industry = $user->supplier_type ?? 'General Sector';

    $data = [
        'result'   => null,
        'error'    => null,
        'country'  => $country,
        'industry' => $industry,
    ];

    if ($request->isMethod('post')) {
        $prompt = "For the country: \"{$country}\" and the industry/sector: \"{$industry}\", "
                . "list the likely data subject areas, including types of sensitive information that would be relevant. "
                . "For each category, provide a brief description. Respond in JSON format as an array of objects, each with 'category' and 'description' keys.";

        try {
            $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant who provides relevant subject categories and short descriptions based on country and industry context.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 3000,
            ]);

            if ($response->successful() && isset($response['choices'][0]['message']['content'])) {
                $data['result'] = $response['choices'][0]['message']['content'];
            } else {
                $msg = is_string($response->body()) ? $response->body() : 'No valid response';
                $data['error'] = 'OpenAI API error: ' . $msg;
            }
        } catch (\Exception $ex) {
            $data['error'] = 'OpenAI Error: ' . $ex->getMessage();
        }
    }
    return view('wizard.subject_categories', $data);
}*/
  
  public function subjectCategories(Request $request)
{
    $ctx = $this->getBusinessContext();

    $country  = $ctx['country'] ?? 'Australia';
    $industry = $ctx['industry'] ?? 'General Sector';

    $data = [
        'result'   => null,
        'error'    => null,
        'country'  => $country,
        'industry' => $industry,
    ];

    if ($request->isMethod('post')) {
        $prompt = "For the country: \"{$country}\" and the industry/sector: \"{$industry}\", "
            . "list the likely data subject areas, including types of sensitive information that would be relevant. "
            . "For each category, provide a brief description. Respond in JSON format as an array of objects, "
            . "each with 'category' and 'description' keys.";
        try {
            $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant who provides relevant subject categories based on country and industry context.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens'  => 3000,
            ]);

            if ($response->successful() && isset($response['choices'][0]['message']['content'])) {
                $data['result'] = $response['choices'][0]['message']['content'];
            } else {
                $msg = is_string($response->body()) ? $response->body() : 'No valid response';
                $data['error'] = 'OpenAI API error: ' . $msg;
            }
        } catch (\Exception $ex) {
            $data['error'] = 'OpenAI Error: ' . $ex->getMessage();
        }
    }
    return view('wizard.subject_categories', $data);
}
  
 

    // Step 5: API
  
  	public function step5()
	{
    $sources = $this->config()->data_sources ?? '';
    $sourcesArr = is_array($sources) ? $sources : [$sources];
    $api = session('wizard.api_config') ?? $this->config()->api_config ?? [];
    $m365 = $this->config()->m365_config_json ?? [];
    $config = $this->config();
    $combo_id = (auth()->id() . ($config->id ?? ''));
    $webhook_url = in_array('M365 - SharePoint & OneDrive', $sourcesArr)
        ? url('/webhook/' . $combo_id) : '';

    // Load config fields (JSON fields) for first (or selected/only) source
    // (If you ever allow multiple sources in future, make this loop/multi-field)
    $selected_source_name = $sourcesArr[0] ?? '';
    $source_ref = DataSourceRef::where('data_source_name', $selected_source_name)->first();
    $config_fields = [];
    if ($source_ref && is_array($source_ref->storage_type_config)) {
        // It's already cast due to your model's $casts
        $config_fields = $source_ref->storage_type_config['required'] ?? [];
    } elseif ($source_ref && $source_ref->storage_type_config) {
        // Old Laravel may have as string, decode
        $fields_arr = json_decode($source_ref->storage_type_config, true);
        $config_fields = $fields_arr['required'] ?? [];
    }

    return view('wizard.step5', compact('sources', 'api', 'm365', 'combo_id', 'webhook_url', 'config', 'config_fields', 'selected_source_name'));
	}
  

  public function step5Post(Request $request)
	{
    // Get the user's selected source name from their config:
    $sources = $this->config()->data_sources ?? '';
    $sourcesArr = is_array($sources) ? $sources : [$sources];
    $selected_source_name = $sourcesArr[0] ?? '';
    $source_ref = DataSourceRef::where('data_source_name', $selected_source_name)->first();

    $config_json = [];
    $config_fields = [];

    // Replicate the config_fields logic from step5()
    if ($source_ref && is_array($source_ref->storage_type_config)) {
        $config_fields = $source_ref->storage_type_config['required'] ?? [];
    } elseif ($source_ref && $source_ref->storage_type_config) {
        $fields_arr = json_decode($source_ref->storage_type_config, true);
        $config_fields = $fields_arr['required'] ?? [];
    }

    // Dynamically build validator rules for those fields
    $validation_fields = [];
    foreach ($config_fields as $field) {
        $input_key = strtolower(str_replace([' ', '-'], '_', $field));
        $validation_fields[$input_key] = 'required|string';
    }

    // Validate and extract data
    $data = count($validation_fields) ? $request->validate($validation_fields) : [];

    // Populate $config_json for each field
    foreach ($config_fields as $field) {
        $input_key = strtolower(str_replace([' ', '-'], '_', $field));
        $config_json[$field] = $data[$input_key] ?? '';
    }

    // Special logic for M365
    if ($selected_source_name == 'M365 - OneDrive, SharePoint & Teams Files' && $config_fields) {
        $config_id = $this->config()->id ?? '';
        $user_id = auth()->id();
        $combo_id = $user_id . $config_id;
        $webhook_url = url('/webhook/' . $combo_id);

        $config_json['webhook_url'] = $webhook_url;
        $config_json['resource'] = 'bulk';
        $config_json['webhook_client_state'] = $combo_id;
    }

    // Save only m365_config_json to the config:
    $save = [
        'm365_config_json' => $config_json
    ];
    
    
    // Set status if user clicked 'Save All & Complete'
    if ($request->input('save_type') === 'complete') {
        $save['status'] = 'complete';
    } else {
        $save['status'] = 'in_progress';
    }
    
    $this->saveWizardToConfig($save);

    // Redirect depending on which button was pressed
    if ($request->input('save_type') === 'complete') {
        // send user to dashboard when complete
        //return redirect('/wizdashboard'); // or route('wizard.dashboard') if you have named route
      	return redirect()->route('wizard.dashboard');
    } else {
        // stay on the current step
        return redirect()->route('wizard.step5');
    }
	}

  
  
  

    // =========== Wizard Finalization ===========
    public function done()
    {
        $all = $this->config();
        session()->forget('wizard.edit_id');
        return view('wizard.done', ['all' => $all]);
    }

    public function dashboard()
    {
        $configs = auth()->user()->dataConfigs()->orderBy('created_at', 'desc')->get();
        return view('wizard.dashboard', compact('configs'));
    }

    public function show($id)
    {
        $config = auth()->user()->dataConfigs()->findOrFail($id);
        return view('wizard.show', compact('config'));
    }

    public function destroy($id)
    {
        $config = auth()->user()->dataConfigs()->findOrFail($id);
        $config->delete();
        return redirect()->route('wizard.dashboard')->with('success', 'Configuration deleted!');
    }

    // ========== Editing ==========
    public function edit($id)
    {
        $config = auth()->user()->dataConfigs()->findOrFail($id);
        session([
            'wizard.data_sources'         => is_array($config->data_sources) ? ($config->data_sources[0] ?? '') : $config->data_sources,
            'wizard.regulations'          => $config->regulations,
            'wizard.metadata'             => $config->metadata,
            'wizard.high_risk_types'      => $config->risk_types['high'] ?? [],
            'wizard.medium_risk_types'    => $config->risk_types['medium'] ?? [],
            'wizard.api_config'           => $config->api_config,
            'wizard.pii_volume_thresholds'=> $config->pii_volume_thresholds ?? [
                'high'=>5, 'medium'=>3, 'low'=>1, 'none'=>0
            ],
            'wizard.pii_volume_category'  => $config->pii_volume_category ?? 'None',
            'wizard.edit_id'              => $config->id,
        ]);
        return redirect()->route('wizard.step1');
    }

    // ========== DB Write Helper ==========
    private function saveWizardToConfig($data)
    {
        $editId = session('wizard.edit_id');
        if ($editId) {
            $config = auth()->user()->dataConfigs()->findOrFail($editId);
            $config->update($data);
            return $config;
        } else {
            return auth()->user()->dataConfigs()->latest()->firstOrCreate([], $data);
        }
    }

    // =========== M365 Python Integration ===========
   /* public function establishM365Link(Request $request, $config_id)
    {
        $config = DataConfig::findOrFail($config_id);
        $m365 = $config->m365_config_json;
        $py_cmd = [
            'python3',
            '/home/cybersecai/htdocs/www.cybersecai.io/webhook/onedrive_sharepoint_renew_webhook.py',
            $m365['tenant_id'],
            $m365['client_id'],
            $m365['client_secret'],
            $m365['webhook_url'],
            $m365['webhook_client_state'],
            $config_id
        ];
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($py_cmd, $descriptorspec, $pipes, '/home/cybersecai/htdocs/www.cybersecai.io/webhook');
        if (!is_resource($process)) {
            return response()->json(['success' => false, 'err' => 'Could not start Python script']);
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($process);
        if ($status === 0) {
            $result = @json_decode($stdout, true);
            return response()->json(['success' => true, 'output' => $result]);
        } else {
            return response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
        }
    }*/

   
  
  
  	public function establishM365Link(Request $request, $config_id)
{
    $config = \App\Models\DataConfig::findOrFail($config_id);
    $m365 = $config->m365_config_json;

    // Defensive: get the data source name in a flexible way (array or string, cleansed)
    $data_source = $config->data_sources ?? '';
    if (is_array($data_source)) {
        $data_source = $data_source[0] ?? '';
    }
    $data_source = trim($data_source, "\"' \t\n\r\0\x0B");

    // Only allow this function for M365 data sources
    if (stripos($data_source, 'm365') === false && stripos($data_source, 'OneDrive') === false && stripos($data_source, 'SharePoint') === false) {
        return response()->json(['success' => false, 'err' => 'This operation is only for M365/OneDrive/SharePoint data sources.']);
    }

    // Only required if data source is M365
    if (!is_array($m365) || empty($m365['tenant_id']) || empty($m365['client_id']) || empty($m365['client_secret'])) {
        return response()->json([
            'success' => false,
            'err' => 'Missing tenant_id, client_id, or client_secret in your M365 configuration. Please complete all required fields on Step 4.'
        ]);
    }

    $py_cmd = [
        'python3',
        '/home/cybersecai/htdocs/www.cybersecai.io/webhook/onedrive_sharepoint_renew_webhook.py',
        $m365['tenant_id'],
        $m365['client_id'],
        $m365['client_secret'],
        $m365['webhook_url'] ?? '',
        $m365['webhook_client_state'] ?? '',
        $config_id
    ];
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($py_cmd, $descriptorspec, $pipes, '/home/cybersecai/htdocs/www.cybersecai.io/webhook');
    if (!is_resource($process)) {
        return response()->json(['success' => false, 'err' => 'Could not start Python script']);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status === 0) {
        $result = @json_decode($stdout, true);
        return response()->json(['success' => true, 'output' => $result]);
    } else {
        return response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
    }
}
  
  
  
  	public function classifyFilesM365(Request $request, $config_id)
	{
    $config = DataConfig::findOrFail($config_id);
    $m365 = $config->m365_config_json;
    $regulations = $config->regulations ?? '[]'; // Default to empty JSON

    $py_cmd = [
        'python3',
        '/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365/1_list_onedrivefiles.py',
        $m365['tenant_id'],
        $m365['client_id'],
        $m365['client_secret'],
        $config_id,
        $regulations // << NEW: pass as 5th arg
    ];

    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($py_cmd, $descriptorspec, $pipes, '/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365');
    if (!is_resource($process)) {
        return response()->json(['success' => false, 'err' => 'Could not start Python script']);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    if ($status === 0) {
        $result = @json_decode($stdout, true);
        return response()->json(['success' => true, 'output' => $result]);
    } else {
        return response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
    }
	}
  
  
  
  public function startClassifying(Request $request, $config_id)
	{
    $basePath = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365";
    $configDir = '';
    \Log::info("[startClassifying] Called with config_id: $config_id. Base search dir: $basePath");

    try {
        $dirIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($dirIterator as $dir) {
            if ($dir->isDir() && $dir->getFilename() == (string)$config_id) {
                $configDir = $dir->getPathname();
                \Log::info("[startClassifying] Found configDir: $configDir for config_id=$config_id");
                break;
            }
        }
        if (!$configDir) {
            \Log::error("[startClassifying] Failed: config directory for id $config_id not found!");
            return response()->json(['success' => false, 'err' => "Config directory not found for id $config_id"]);
        }

        $pythonScript = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365/2_M365_content_extraction_PII_identification.py';
        $py_cmd = [
            'python3',
            $pythonScript,
            $config_id // Pass as argument!
        ];
        \Log::info("[startClassifying] Executing: " . implode(' ', $py_cmd) . " (cwd: $configDir)");

        $process = proc_open($py_cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $configDir);

        if (!is_resource($process)) {
            \Log::error("[startClassifying] Could not start Python script for $config_id.");
            return response()->json(['success' => false, 'err' => 'Could not start Python script']);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($process);

        \Log::info("[startClassifying] Script exit status: $status, stdout: ".substr($stdout,0,1000));
        if($stderr) \Log::error("[startClassifying] stderr: " . substr($stderr,0,1000));

        if ($status === 0) {
            return response()->json(['success' => true, 'output' => $stdout]);
        } else {
            return response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
        }
    } catch (\Exception $e) {
        \Log::error("[startClassifying][EXCEPTION] " . $e->getMessage());
        return response()->json(['success' => false, 'err' => $e->getMessage()]);
    }
	}
  
  
  	// =====SMB Functions - Function 1 to list =============
  
  	
  	public function classifyFilesSMB(Request $request, $config_id)
{
    try {
        $config = \App\Models\DataConfig::findOrFail($config_id);
        // Many backends use m365_config_json for *every* kind of config; adapt as needed
        $smb = $config->m365_config_json;
        // Fallback: if you later use $config->smb_config_json instead, adapt accordingly!
        $regulations = $config->regulations ?? '[]';

        // Extracting using the correct keys as per your stored config
        $server     = $smb['smb_server']  ?? '';
        $share      = $smb['share_name']  ?? '';
        $username   = $smb['username']    ?? '';
        $password   = $smb['password']    ?? '';
        // Optionals
        $domain     = $smb['domain']      ?? '';
        $base_path  = $smb['base_path']   ?? '';

        // For full trace
        \Log::info("[classifyFilesSMB] Using config", [
            'config_id'  => $config_id,
            'server'     => $server,
            'share'      => $share,
            'username'   => $username,
            'domain'     => $domain,
            'base_path'  => $base_path
        ]);

        $py_cmd = [
            'python3',
            '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB/1_list_SMBfiles.py',
            $server,
            $username,
            $password,
            $share,
            $domain,
            $base_path,
            $config_id,
            $regulations
        ];

        \Log::info("[classifyFilesSMB] Launching SMB Python script for config_id={$config_id}", [
            'py_cmd' => $py_cmd,
            'cwd' => '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB',
        ]);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($py_cmd, $descriptorspec, $pipes, '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB');

        if (!is_resource($process)) {
            \Log::error("[classifyFilesSMB] Could not start Python script", [
                'py_cmd' => $py_cmd
            ]);
            return response()->json(['success' => false, 'err' => 'Could not start Python script']);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        \Log::info("[classifyFilesSMB] Python process completed", [
            'status' => $status,
            'stdout_first1k' => substr($stdout, 0, 1000),
            'stderr_first1k' => substr($stderr, 0, 1000),
        ]);

        if ($status === 0) {
            $result = @json_decode($stdout, true);
            \Log::info("[classifyFilesSMB] Python returned result", ['result_sample' => is_array($result) ? array_slice($result, 0, 10) : $result]);
            return response()->json(['success' => true, 'output' => $result]);
        } else {
            \Log::error("[classifyFilesSMB] Python script failed", [
                'status' => $status,
                'stdout' => $stdout,
                'stderr' => $stderr
            ]);
            return response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
        }
    } catch (\Exception $e) {
        \Log::error("[classifyFilesSMB][EXCEPTION] " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['success' => false, 'err' => $e->getMessage()]);
    }
}
  
  
  	// =====SMB Functions - Function 2 to extract and classify =============
  
  	public function startClassifyingSMB(Request $request, $config_id) {
    $basePath = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB";
    $configDir = '';
    \Log::info("[startClassifyingSMB] Called with config_id: $config_id. Base search dir: $basePath");

    try {
        $dirIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($dirIterator as $dir) {
            if ($dir->isDir() && $dir->getFilename() == (string)$config_id) {
                $configDir = $dir->getPathname();
                \Log::info("[startClassifyingSMB] Found configDir: $configDir for config_id=$config_id");
                break;
            }
        }
        if (!$configDir) {
            \Log::error("[startClassifyingSMB] Failed: config directory for id $config_id not found!");
            return response()->json(['success' => false, 'err' => "Config directory not found for id $config_id"]);
        }

        $pythonScript = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB/2_smb_content_extract_compliance.py';
        $py_cmd = [
            'python3',
            $pythonScript,
            $config_id
        ];
        \Log::info("[startClassifyingSMB] Executing: " . implode(' ', $py_cmd) . " (cwd: $configDir)");

        $process = proc_open($py_cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $configDir);

        if (!is_resource($process)) {
            \Log::error("[startClassifyingSMB] Could not start Python script for $config_id.");
            return response()->json(['success' => false, 'err' => 'Could not start Python script']);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($process);

        \Log::info("[startClassifyingSMB] Script exit status: $status, stdout: ".substr($stdout,0,1000));
        if($stderr) \Log::error("[startClassifyingSMB] stderr: " . substr($stderr,0,1000));

        if ($status === 0) {
            return response()->json(['success' => true, 'output' => $stdout]);
        } else {
            return response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
        }
    } catch (\Exception $e) {
        \Log::error("[startClassifyingSMB][EXCEPTION] " . $e->getMessage());
        return response()->json(['success' => false, 'err' => $e->getMessage()]);
    }
	}
  
  
  
  	// ======== Laravel Controller to List/Export NFS Files - Function 1=======
  	
  	public function classifyFilesNFS(Request $request, $config_id) {
    $config = DataConfig::findOrFail($config_id);
    $nfs = $config->nfs_config_json; // ['path' => '/mnt/nfs_share', ...]
    $regulations = $config->regulations ?? '[]';
    $baseWebhook = '/home/cybersecai/htdocs/www.cybersecai.io/webhook';

    $py_cmd = [
        'python3',
        "$baseWebhook/NFS/1_list_nfsfiles.py",
        $nfs['path'], // NFS/source dir
        $config_id,
        $regulations,
        $baseWebhook
    ];

    $process = proc_open($py_cmd, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, "$baseWebhook/NFS");
    if (!is_resource($process)) {
        return response()->json(['success' => false, 'err' => 'Could not start Python script']);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    return $status === 0
        ? response()->json(['success' => true, 'output' => json_decode($stdout, true)])
        : response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
	}
  
  
  	// ======== NFS Extraction/Classification - Function 2=======
  
  	public function startClassifyingNFS(Request $request, $config_id) {
    $basePath = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/NFS";
    $configDir = '';
    \Log::info("[startClassifyingNFS] Called with config_id: $config_id. Base search dir: $basePath");
    try {
        $dirIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($dirIterator as $dir) {
            if ($dir->isDir() && $dir->getFilename() == (string)$config_id) {
                $configDir = $dir->getPathname();
                \Log::info("[startClassifyingNFS] Found configDir: $configDir for config_id=$config_id");
                break;
            }
        }
        if (!$configDir) {
            \Log::error("[startClassifyingNFS] Failed: config directory for id $config_id not found!");
            return response()->json(['success' => false, 'err' => "Config directory not found for id $config_id"]);
        }

        $baseWebhook = '/home/cybersecai/htdocs/www.cybersecai.io/webhook';
        $pythonScript = "$baseWebhook/NFS/2_nfs_content_extract_compliance.py";
        $py_cmd = [
            'python3',
            $pythonScript,
            $config_id
        ];
        \Log::info("[startClassifyingNFS] Executing: " . implode(' ', $py_cmd) . " (cwd: $configDir)");

        $process = proc_open($py_cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $configDir);

        if (!is_resource($process)) {
            \Log::error("[startClassifyingNFS] Could not start Python script for $config_id.");
            return response()->json(['success' => false, 'err' => 'Could not start Python script']);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($process);

        \Log::info("[startClassifyingNFS] Script exit status: $status, stdout: ".substr($stdout,0,1000));
        if($stderr) \Log::error("[startClassifyingNFS] stderr: " . substr($stderr,0,1000));

        return $status === 0
            ? response()->json(['success' => true, 'output' => $stdout])
            : response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);
    } catch (\Exception $e) {
        \Log::error("[startClassifyingNFS][EXCEPTION] " . $e->getMessage());
        return response()->json(['success' => false, 'err' => $e->getMessage()]);
    }
	}
  
  
  
  	// ======== Laravel Controller to List/Export AWS S3 Files - Function 1=======

public function classifyFilesS3(Request $request, $config_id)
{
    \Log::info("Starting classifyFilesS3 with config_id: {$config_id}");

    // Fetch config from the database
    $config = DataConfig::find($config_id);
    if (!$config) {
        \Log::error("Config not found for id: {$config_id}");
        return response()->json([
            'success' => false,
            'error' => 'Configuration not found.'
        ], 404);
    }

    // Retrieve and validate the S3 config
    $s3JsonRaw = $config->m365_config_json;
    \Log::debug("Retrieved m365_config_json: ", ['json' => $s3JsonRaw]);

    // Handle case where already array (cast?) -- or string, so we decode as needed
    if (is_array($s3JsonRaw)) {
        $raw = $s3JsonRaw;
    } else {
        $raw = json_decode($s3JsonRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error("Malformed JSON in m365_config_json", [
                'id' => $config_id,
                'error' => json_last_error_msg()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Malformed S3 configuration JSON.',
            ], 400);
        }
    }

    $s3Config = $raw['json'] ?? $raw;
    if (!is_array($s3Config)) {
        \Log::error("Unexpected S3 config format in m365_config_json for id: {$config_id}");
        return response()->json([
            'success' => false,
            'error' => 'Invalid S3 configuration structure.',
        ], 422);
    }

    foreach (['aws_access_key_id', 'aws_secret_access_key'] as $required) {
        if (!isset($s3Config[$required]) || empty($s3Config[$required])) {
            \Log::error("Missing required S3 config key: {$required} in config_id: {$config_id}");
            return response()->json([
                'success' => false,
                'error' => "Missing required S3 config key: {$required}."
            ], 422);
        }
    }

    // Prepare parameters for your updated script
    $region      = $s3Config['region'] ?? 'us-east-1';
    $access_key  = $s3Config['aws_access_key_id'];
    $secret_key  = $s3Config['aws_secret_access_key'];
    $regulations = $config->regulations ?? '[]';

    // If you want to force single-bucket, add $bucket_name to this array BELOW;
    // for all-bucket scan, OMIT bucket name.
    $py_cmd = [
        'python3',
        '/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3/1_list_s3files_parallel.py',      // <-- Script name updated!
        $access_key, $secret_key, $config_id, $regulations, $region
        // (Optionally add $bucket_name as 7th arg IF you want to override)
    ];
    \Log::info('Python command: ' . implode(' ', array_map('escapeshellarg', $py_cmd)));

    $process = proc_open($py_cmd, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, '/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3');

    if (!is_resource($process)) {
        \Log::error("Failed to start Python script for config_id: {$config_id}");
        return response()->json([
            'success' => false,
            'error' => 'Could not start Python script'
        ], 500);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $status = proc_close($process);

    if ($status === 0) {
        \Log::info("Python script succeeded for config_id: {$config_id}");
        $output = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::warning("Python script output is not valid JSON for config_id: {$config_id}", [
                'stdout' => $stdout
            ]);
            $output = $stdout;
        }
        return response()->json(['success' => true, 'output' => $output]);
    } else {
        \Log::error("Python script failed for config_id: {$config_id}", [
            'stderr' => $stderr,
            'stdout' => $stdout
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Python script failed.',
            'stderr' => $stderr,
            'stdout' => $stdout
        ], 500);
    }
}
  

  
  	// ======== Laravel Controller to Extract & Classify AWS S3 Files - Function 2=======
  
	public function startClassifyingS3(Request $request, $config_id) {
    $basePath = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3";
    $configDir = '';
    \Log::info("[startClassifyingS3] Called with config_id: $config_id. Base search dir: $basePath");
    $dirIterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($dirIterator as $dir) {
        if ($dir->isDir() && $dir->getFilename() == (string)$config_id) {
            $configDir = $dir->getPathname();
            \Log::info("[startClassifyingS3] Found configDir: $configDir for config_id=$config_id");
            break;
        }
    }
    if (!$configDir) return response()->json(['success'=>false,'err'=>"Config directory not found for id $config_id"]);
    $pythonScript = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3/2_s3_content_extract_compliance_parallel.py';
    $py_cmd = ['python3', $pythonScript, $config_id];
    $process = proc_open($py_cmd, [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $configDir);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    return $status === 0
        ? response()->json(['success'=>true,'output'=>$stdout])
        : response()->json(['success'=>false,'err'=>$stderr,'stdout'=>$stdout]);
	}
  
  
  	// ======== Laravel Controller to List/Export Google Drive Files - Function 1=======
  
  	public function classifyFilesGDrive(Request $request, $config_id) {
    try {
        $config = DataConfig::findOrFail($config_id);

        // Get the config array from m365_config_json
        $gdrive = $config->m365_config_json;
        if (!is_array($gdrive)) {
            \Log::error("[classifyFilesGDrive] m365_config_json is missing or not an array.", [
                'config_id' => $config_id,
                'user_id' => $config->user_id ?? null,
                'actual_value' => $gdrive
            ]);
            return response()->json([
                'success' => false,
                'err' => 'Google Drive configuration not found. Please complete the required configuration in the wizard.'
            ]);
        }

        // Extract and check values
        $serviceAccountJson = $gdrive['service_account_credentials_json'] ?? null;
        $folderId = $gdrive['gdrive_folder_id'] ?? null;

        if (empty($serviceAccountJson) || empty($folderId)) {
            \Log::error("[classifyFilesGDrive] Missing service_account_credentials_json or gdrive_folder_id.", [
                'config_id' => $config_id,
                'user_id' => $config->user_id ?? null,
                'm365_config_json' => $gdrive
            ]);
            return response()->json([
                'success' => false,
                'err' => 'Google Drive configuration is incomplete. Please provide both the service account credentials and folder ID.'
            ]);
        }

        $regulations = $config->regulations ?? '[]';

        // Command to call Python script
        $py_cmd = [
            'python3',
            '/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE/1_list_gdrivefiles_parallel.py',
            $serviceAccountJson,   // Pass as string (works for in-script json.loads() in py)
            $config_id,
            $regulations,
            $folderId
        ];

        \Log::info("[classifyFilesGDrive] Launching Python script for GDrive.", [
            'config_id' => $config_id,
            'user_id' => $config->user_id ?? null,
            'gdrive_folder_id' => $folderId,
            'py_cmd_preview' => array_slice($py_cmd, 0, 3), // Don't log credentials!
            'cwd' => '/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE'
        ]);

        // Launch Python process
        $process = proc_open($py_cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, '/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE');

        if (!is_resource($process)) {
            \Log::error("[classifyFilesGDrive] Failed to start Python script.", [
                'config_id' => $config_id,
                'user_id' => $config->user_id ?? null,
                'py_cmd_preview' => array_slice($py_cmd, 0, 3)
            ]);
            return response()->json(['success' => false, 'err' => 'Could not start Python script']);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        \Log::info("[classifyFilesGDrive] Python script completed.", [
            'config_id' => $config_id,
            'script_status' => $status,
            'stdout_first_500' => substr($stdout, 0, 500),
            'stderr_first_500' => substr($stderr, 0, 500)
        ]);

        return $status === 0
            ? response()->json(['success' => true, 'output' => json_decode($stdout, true)])
            : response()->json(['success' => false, 'err' => $stderr, 'stdout' => $stdout]);

    } catch (\Exception $e) {
        \Log::error("[classifyFilesGDrive][EXCEPTION] " . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'config_id' => $config_id
        ]);
        return response()->json([
            'success' => false,
            'err' => 'Unexpected error: ' . $e->getMessage()
        ]);
    }
}
  
  	// ======== Laravel Controller to Extract & Classify Google Drive Files - Function 2=======

	public function startClassifyingGDrive(Request $request, $config_id) {
    $basePath = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE";
    $configDir = '';
    \Log::info("[startClassifyingGDrive] Called with config_id: $config_id. Base search dir: $basePath");
    $dirIterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($dirIterator as $dir) {
        if ($dir->isDir() && $dir->getFilename() == (string)$config_id) {
            $configDir = $dir->getPathname();
            \Log::info("[startClassifyingGDrive] Found configDir: $configDir for config_id=$config_id");
            break;
        }
    }
    if (!$configDir) return response()->json(['success'=>false,'err'=>"Config directory not found for id $config_id"]);
    $pythonScript = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE/2_gdrive_content_extract_compliance_parallel.py';
    $py_cmd = ['python3', $pythonScript, $config_id];
    $process = proc_open($py_cmd, [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $configDir);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    return $status === 0
        ? response()->json(['success'=>true,'output'=>$stdout])
        : response()->json(['success'=>false,'err'=>$stderr,'stdout'=>$stdout]);
}
  
  


/**
 * Launches or updates PII/PHI classification for a database config.
 * @param  int $config_id DataConfig PK (must contain valid DB connection info)
 */
public function classifyDatabase(Request $request, $config_id)
{
    \Log::info("[classifyDatabase] Starting database classification for config_id: $config_id");

    $config = DataConfig::findOrFail($config_id);

    $dbJson = $config->m365_config_json;
    $dbType = $config->db_type ?? 'mysql';

    \Log::info("[classifyDatabase] Loaded DataConfig for config_id=$config_id with db_type=$dbType");

    $dbArr = is_array($dbJson) ? $dbJson : json_decode($dbJson, true);
    if (!is_array($dbArr)) {
        \Log::error("[classifyDatabase] Malformed db_config_json for config_id=$config_id");
        return response()->json(['success' => false, 'err' => 'Malformed database config.']);
    }

    foreach (['host', 'port', 'user', 'db_password'] as $key) {
        if (!isset($dbArr[$key]) || !$dbArr[$key]) {
            \Log::error("[classifyDatabase] Missing required DB field '$key' for config_id=$config_id");
            return response()->json(['success' => false, 'err' => "Missing required database field: $key."]);
        }
    }

    $database = $dbArr['database'] ?? '';
    \Log::info("[classifyDatabase] Connecting with host={$dbArr['host']}, user={$dbArr['user']}, database={$database}, type=$dbType for config_id=$config_id");

    try {
        $resp = Http::timeout(1200)
            ->asJson()
            ->post('http://127.0.0.1:8205/db/discover-columns', [
                'id'        => $config_id,
                'db_type'   => $dbType,
                'host'      => $dbArr['host'],
                'port'      => (int)$dbArr['port'],
                'user'      => $dbArr['user'],
                'password'  => $dbArr['db_password'],
                'database'  => $database,
            ]);
        \Log::info("[classifyDatabase] FastAPI response", ['result' => $resp->json()]);
        return response()->json($resp->json());
    } catch (\Throwable $e) {
        \Log::error("[classifyDatabase][EXCEPTION] " . $e->getMessage());
        return response()->json(['success' => false, 'err' => $e->getMessage()]);
    }
}

/**
 * DSR search for a specific user value across LLM-tagged privacy columns for a config/database.
 */
public function dbDSRFindUser(Request $request, $config_id)
{
    \Log::info("[dbDSRFindUser] Starting DSR user-value search for config_id: $config_id");

    $config = DataConfig::findOrFail($config_id);
    $dbJson = $config->m365_config_json;
    $dbType = $config->db_type ?? 'mysql';
    $dbArr = is_array($dbJson) ? $dbJson : json_decode($dbJson, true);

    foreach (['host', 'port', 'user', 'password'] as $key) {
        if (!isset($dbArr[$key]) || !$dbArr[$key]) {
            \Log::error("[dbDSRFindUser] Missing DB field $key for config_id=$config_id");
            return response()->json(['success' => false, 'err' => "Missing required database field: $key."]);
        }
    }
    $database = $dbArr['database'] ?? '';
    $user_value = $request->input('search_value') ?? '';

    if (!$user_value) {
        \Log::warning("[dbDSRFindUser] No search_value provided for config_id=$config_id");
        return response()->json(['success' => false, 'err' => 'No user value to search provided.']);
    }

    \Log::info("[dbDSRFindUser] Searching value '{$user_value}' in DB config_id=$config_id, db_type=$dbType, host={$dbArr['host']}");

    try {
        $resp = Http::timeout(600)
            ->asJson()
            ->post('http://127.0.0.1:8205/db/find-user', [
                'id'        => $config_id,
                'db_type'   => $dbType,
                'host'      => $dbArr['host'],
                'port'      => (int)$dbArr['port'],
                'user'      => $dbArr['user'],
                'password'  => $dbArr['password'],
                'database'  => $database,
                'user_search_value' => $user_value
            ]);
        \Log::info("[dbDSRFindUser] FastAPI response", ['result' => $resp->json()]);
        return response()->json($resp->json());
    } catch (\Throwable $e) {
        \Log::error("[dbDSRFindUser][EXCEPTION] " . $e->getMessage());
        return response()->json(['success' => false, 'err' => $e->getMessage()]);
    }
}
  
  	// =========== JSON Displays ===========
  
  	// Recursively get all .json files in lowest "graph" folder(s) under a given config id
  
  
private function getJsonFilesForUserConfigs()
{
    $user = auth()->user();

    $configIDs = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();
    $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/';
    $jsonFiles = [];

    \Log::info("Scanning for .json files for user_id [{$user->id}]. ConfigIDs: " . implode(',', $configIDs));

    foreach ($configIDs as $id) {
        foreach (['M365', 'SMB', 'S3'] as $type) {
            $graphPath = "{$basePath}{$type}/{$id}/graph/";
            \Log::info("Checking graphPath: {$graphPath}");
            if (is_dir($graphPath)) {
                \Log::info("Directory exists: {$graphPath}");
                $files = glob($graphPath . '*.json');
                if ($files) {
                    foreach ($files as $jsonFile) {
                        \Log::info("Found .json file: {$jsonFile}");
                        $jsonFiles[] = $jsonFile;
                    }
                } else {
                    \Log::info("No .json files found in: {$graphPath}");
                }
            } else {
                \Log::info("Directory does NOT exist: {$graphPath}");
            }
        }
    }

    \Log::info("Total .json files found: " . count($jsonFiles));
    return $jsonFiles;
}
  
  
  	private function detectSourceFromPath($path)
{
    // Primitive but effective: /webhook/SMB/17/graph/...
    if (stripos($path, '/SMB/') !== false) return 'SMB';
    if (stripos($path, '/M365/') !== false) return 'M365';
    if (stripos($path, '/S3/') !== false) return 'AWS S3';
    // Add more as needed...
    return 'unknown';
}

	  
  
  // Route: GET /yourroute/file-graph-table


  public function fileGraphTable(Request $request)
{
    $filterGroups = $this->getFilterGroupsAndSources();
    // Don't encode as JSON here!
    return view('wizard.file_graph_datatable', [
        'filterGroups' => $filterGroups['groups'],
        'sourceLabels' => $filterGroups['sources'],
    ]);
}


  
  public function fileSummaryPyramid(Request $request)
{
    // For very high scale, only scan a small sample to offer initial filter groups for the UI.
    $jsonFiles = array_slice($this->getJsonFilesForUserConfigs(), 0, 100);

    $userSiteEntities = [];
    $otherSources = [];
    $hasUnassigned = false;

    foreach ($jsonFiles as $file) {
        $content = @file_get_contents($file);
        if ($content === false) continue;
        $json = json_decode($content, true);
        $rows = (is_array($json) && isset($json[0]) && is_array($json[0])) ? $json : (is_array($json) ? [$json] : []);
        foreach ($rows as $item) {
            $src = $this->detectSourceFromPath($file);
            if (isset($item['user_id'])) {
                $userSiteEntities['User-' . $item['user_id']] = true;
            } elseif (isset($item['site_id'])) {
                $userSiteEntities['Site-' . $item['site_id']] = true;
            } elseif ($src) {
                $otherSources['SRC_' . $src] = ucfirst($src) . ' Files';
            } else {
                $hasUnassigned = true;
            }
        }
    }
    $filterGroups = [];
    if (count($userSiteEntities)) {
        $filterGroups[] = [ 'label' => 'User/Site Assignments', 'values' => array_keys($userSiteEntities) ];
    }
    if (count($otherSources)) {
        $filterGroups[] = [ 'label' => 'Other Data Sources', 'values' => array_keys($otherSources) ];
    }
    if ($hasUnassigned) {
        $filterGroups[] = [ 'label' => 'No Assignment', 'values' => [ '__UNASSIGNED__' ] ];
    }

    return view('wizard.filesummary_pyramid', [
        // No allData sent!
        'filterGroups' => json_encode($filterGroups),
        'sourceLabels' => json_encode($otherSources),
    ]);
}

  
  public function apiPyramidStats(Request $request)
{
    $entity = $request->query('entity');
    $riskLevel = $request->query('riskLevel');
    $page = (int) $request->query('page', 1);
    $pageSize = (int) $request->query('pageSize', 50);

    $jsonFiles = $this->getJsonFilesForUserConfigs();
    $riskCounts = ['high' => 0, 'medium' => 0, 'low' => 0, 'none' => 0];
    $files = [];
    foreach ($jsonFiles as $file) {
        $content = @file_get_contents($file); if ($content === false) continue;
        $json = json_decode($content, true);
        $rows = (is_array($json) && isset($json[0]) && is_array($json[0])) ? $json : (is_array($json) ? [$json] : []);
        foreach ($rows as $item) {
            $src = $this->detectSourceFromPath($file);
            $item['_entity'] = $this->extractEntity($item, $src);
            $risk = $this->extractRiskLevel($item['llm_response'] ?? '');
            if ($entity && $item['_entity'] !== $entity) continue;
            $riskCounts[$risk]++;
            if ($riskLevel && $risk === $riskLevel) {
                $files[] = [
                    'file_name' => $item['file_name']??'',
                    'file_type' => $item['file_type']??'',
                    '_datasource' => $src,
                    'size_bytes' => $item['size_bytes']??0,
                    'llm_response' => $item['llm_response']??'',
                ];
            }
        }
    }

    // For riskLevel panel: return only a page
    $filesTotal = count($files);
    $start = max(0, ($page - 1) * $pageSize);
    $filesPage = array_slice($files, $start, $pageSize);

    return response()->json([
        'counts' => $riskCounts,
        'files'  => $riskLevel ? $filesPage : [],
        'filesTotal' => $riskLevel ? $filesTotal : 0,
        'page' => $page,
        'pageSize' => $pageSize,
    ]);
}
  
  // Route: GET /api/files-table
    
  public function apiFilesTable(Request $request)
{
    $page = max(1, (int) $request->input('page', 1));
    $pageSize = max(1, (int) $request->input('pageSize', 50));
    $entity = $request->input('entity');
    $riskLevel = $request->input('riskLevel');
    $search = trim($request->input('search', ''));

    $jsonFiles = $this->getJsonFilesForUserConfigs();
    $jsonFiles = array_slice($jsonFiles, 0, 100);
    $results = [];
    $total = 0;

    $startIndex = ($page - 1) * $pageSize;
    $endIndex = $startIndex + $pageSize;

    $matchedIndex = 0;
    foreach ($jsonFiles as $file) {
        $content = @file_get_contents($file);
        if ($content === false) continue;
        $json = json_decode($content, true);
        if (!is_array($json)) continue;
        $rows = (isset($json[0]) && is_array($json[0])) ? $json : [$json];

        foreach ($rows as $row) {
            $row['_datasource'] = $this->detectSourceFromPath($file);
            $row['_entity'] = $this->extractEntity($row);

            // --- Robust risk extraction section (use your best PHP extractRiskLevel) ---
            $rowRisk = $this->extractRiskLevel($row['llm_response'] ?? '');

            if ($entity && $row['_entity'] !== $entity) continue;
            if ($riskLevel && $rowRisk !== $riskLevel) continue;
            if ($search && (!isset($row['file_name']) || stripos($row['file_name'], $search) === false)) continue;

            if ($matchedIndex >= $startIndex && $matchedIndex < $endIndex) {
                // Optionally pass through fields you want returned
                $results[] = $row;
            }
            $matchedIndex++;
        }
    }
    $total = $matchedIndex;

    return response()->json([
        'data' => $results,
        'recordsTotal' => $total,
        'recordsFiltered' => $total,
    ]);
}
     private function extractEntity($item)
	{
    	if (isset($item['user_id'])) return 'User-' . $item['user_id'];
    	if (isset($item['site_id'])) return 'Site-' . $item['site_id'];
    	if (isset($item['_datasource']) && $item['_datasource']) return 'SRC_' . $item['_datasource'];
    	return '__UNASSIGNED__';
	}
  
	
  
  
  private function extractRiskLevel($llm_response)
{
    if (!$llm_response) return 'none';

    $clean = trim((string)$llm_response);

    // Remove double quotes if double-encoded
    if (substr($clean, 0, 1) === '"' && substr($clean, -1) === '"') {
        $clean = substr($clean, 1, -1);
    }

    // Try to parse as JSON
    $json = json_decode($clean, true);
    if (is_array($json)) {
        // 1) Prefer "overall_risk_rating"
        if (!empty($json['overall_risk_rating'])) {
            $risk = strtolower($json['overall_risk_rating']);
            if (in_array($risk, ['high', 'medium', 'low', 'none'])) return $risk;
        }
        // 2) If results array, find max
        if (!empty($json['results']) && is_array($json['results'])) {
            $rank = ['high'=>3,'medium'=>2,'low'=>1,'none'=>0];
            $max = 'none';
            foreach ($json['results'] as $result) {
                if (!empty($result['risk'])) {
                    $risk = strtolower($result['risk']);
                    if (isset($rank[$risk]) && $rank[$risk] > $rank[$max]) $max = $risk;
                }
            }
            return $max;
        }
    }

    // 3) Fallback: regex for plaintext LLM
    $riskPattern = '/overall\s+(?:risk\s+rating|rating)\s*(?:is)?\s*:?[\s*_`]*\**\s*(high|medium|low|none)\s*\**/i';
    if (preg_match($riskPattern, $clean, $m) && !empty($m[1])) {
        $risk = strtolower($m[1]);
        if (in_array($risk, ['high', 'medium', 'low', 'none'])) return $risk;
    }

    // 4) Legacy: scan all lines for any mention
    $lastRisk = null;
    if (preg_match_all($riskPattern, $clean, $matches) && !empty($matches[1])) {
        $lastRisk = strtolower(end($matches[1]));
    }
    if ($lastRisk && in_array($lastRisk, ['high', 'medium', 'low', 'none'])) return $lastRisk;
    return 'none';
}
  
  public function auditorPersonaDashboard()
{
    return view('persona.auditorpersona');
}
  
    public function riskPersonaDashboard()
{
    return view('persona.riskpersona');
}
  
    public function cyberPersonaDashboard()
{
    return view('persona.cybersecuritypersona');
}
  
  
   /**
     * Parse compliance llm_response into structured data.
     */
private function parseLlmResponse($llm_response)
{
    // Try to decode as JSON first for new LLM responses
    $decoded = json_decode($llm_response, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Extract overall risk (prefer overall_risk_rating)
        $overallRisk =
            !empty($decoded['overall_risk_rating']) ? strtoupper($decoded['overall_risk_rating']) :
                ( !empty($decoded['overall_risk']) ? strtoupper($decoded['overall_risk']) : 'NONE' );

        // Build summary table from results array
        $summary_table = [];
        if (!empty($decoded['results']) && is_array($decoded['results'])) {
            foreach ($decoded['results'] as $row) {
                $summary_table[] = [
                    'standard'     => isset($row['standard']) ? $row['standard'] : '',
                    'jurisdiction' => isset($row['jurisdiction']) ? $row['jurisdiction'] : '',
                    'data_types'   => isset($row['detected_fields']) && is_array($row['detected_fields'])
                        ? implode(", ", $row['detected_fields'])
                        : (isset($row['data_types']) ? $row['data_types'] : ''),
                    'risk_rating'  => isset($row['risk']) ? $row['risk'] : (isset($row['risk_rating']) ? $row['risk_rating'] : ''),
                ];
            }
        }

        // Notes
        $notes = isset($decoded['auditor_agent_view']) ? trim($decoded['auditor_agent_view']) : '';

        // New fields - safe checks, normalization
        $data_classification = isset($decoded['data_classification']) ? $decoded['data_classification'] : '';
        $cyber_proposed_controls = [];
        if (isset($decoded['cyber_proposed_controls'])) {
            if (is_array($decoded['cyber_proposed_controls'])) {
                $cyber_proposed_controls = $decoded['cyber_proposed_controls'];
            } elseif (is_string($decoded['cyber_proposed_controls']) && strlen(trim($decoded['cyber_proposed_controls'])) > 0) {
                $cyber_proposed_controls = [ $decoded['cyber_proposed_controls'] ];
            }
        }
        $likely_data_subject_area = isset($decoded['likely_data_subject_area']) ? $decoded['likely_data_subject_area'] : '';

        return [
            'overall_risk'              => $overallRisk,
            'summary_table'             => $summary_table,
            'notes'                     => $notes,
            'data_classification'       => $data_classification,
            'cyber_proposed_controls'   => $cyber_proposed_controls,
            'likely_data_subject_area'  => $likely_data_subject_area,
        ];
    }

    // ----- Fallback: legacy (markdown/text) ---------
    $riskPattern = '/overall\s+(?:risk\s+rating|rating)\s*(?:is)?\s*:?\s*[\s*_`]*\**\s*(high|medium|low|none)\s*\**/i';
    $lines = preg_split('/\r\n|\r|\n/', (string)$llm_response);
    $overallRisk = null;
    for ($i = count($lines)-1; $i >= 0; $i--) {
        if (preg_match($riskPattern, $lines[$i], $m)) {
            $val = strtolower($m[1]);
            if (in_array($val, ['high','medium','low','none'])) {
                $overallRisk = $val;
                break;
            }
        }
    }
    if (!$overallRisk) {
        preg_match_all($riskPattern, $llm_response, $matches);
        if (!empty($matches[1])) {
            $overallRisk = strtolower(end($matches[1]));
        }
    }
    if (!$overallRisk) $overallRisk = "none";
    preg_match('/\| Standard.*?\|\n([\s\S]+?)\n[\*\-]{2,}/', $llm_response, $table);
    $summary_table = [];
    if (!empty($table[1])) {
        $lines = array_map('trim', explode("\n", $table[1]));
        foreach ($lines as $line) {
            $cols = array_map('trim', explode('|', trim($line, '| ')));
            if (count($cols) >= 4 && strtolower($cols[0]) != 'standard') {
                $summary_table[] = [
                    'standard'     => $cols[0],
                    'jurisdiction' => $cols[1],
                    'data_types'   => $cols[2],
                    'risk_rating'  => $cols[3],
                ];
            }
        }
    }
    $notes = '';
    if (preg_match('/Notes.*?\n([\s\S]+?)(\n(\*{2,}|-{2,}|={2,}|\#)|$)/i', $llm_response, $notesMatch)) {
        $notes = trim($notesMatch[1]);
    }

    return [
        'overall_risk'              => strtoupper($overallRisk),
        'summary_table'             => $summary_table,
        'notes'                     => $notes,
        'data_classification'       => '',
        'cyber_proposed_controls'   => [],
        'likely_data_subject_area'  => '',
    ];
}
  	
  
    /**
     * Normalise permissions for blade (ALWAYS returns an array, safe for missing/bad data)
     */
    private function normalisePermissions($permissions)
    {
        $result = [];
        if (!is_array($permissions)) {
            return $result;
        }
        foreach ($permissions as $perm) {
            if (!is_array($perm)) {
                continue;
            }

            $types = isset($perm['roles']) && is_array($perm['roles']) ? $perm['roles'] : [];
            $type = $types ? implode(',', $types) : '';

            $displayName =
                $perm['grantedToV2']['siteGroup']['displayName'] ??
                $perm['grantedToV2']['group']['displayName'] ??
                $perm['grantedTo']['user']['displayName'] ??
                '-';

            $email =
                $perm['grantedToV2']['group']['email'] ??
                $perm['grantedToV2']['siteUser']['email'] ??
                $perm['grantedTo']['user']['email'] ??
                '';

            $id =
                $perm['grantedToV2']['siteGroup']['id'] ??
                $perm['grantedToV2']['siteUser']['id'] ??
                $perm['grantedTo']['user']['id'] ??
                '';

            $login =
                $perm['grantedToV2']['siteGroup']['loginName'] ??
                $perm['grantedToV2']['siteUser']['loginName'] ??
                '';

            $result[] = [
                'role' => $type,
                'granted_to' => $displayName,
                'email' => $email,
                'id' => $id,
                'login' => $login
            ];
        }
        return $result;
    }

    /** Count broad-permission files (any with "read" for group "Visitors"); completely safe if permissions missing */
    private function countBroadPermissionFiles($allFiles)
    {
        $count = 0;
        foreach ($allFiles as $file) {
            if (!isset($file['permissions']) || !is_array($file['permissions'])) {
                continue;
            }
            foreach ($file['permissions'] as $perm) {
                if (isset($perm['role']) && stripos($perm['role'], 'read') !== false
                    && isset($perm['granted_to']) && stripos($perm['granted_to'], 'visitor') !== false) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }
  
  private function getFilterGroupsAndSources()
{
    // Only scan first ~N files for initial entities/sources to keep it quick
    $jsonFiles = array_slice($this->getJsonFilesForUserConfigs(), 0, 100);
    $userSiteEntities = [];
    $otherSources = [];
    $hasUnassigned = false;

    foreach ($jsonFiles as $file) {
        $fh = @fopen($file, 'r');
        if (!$fh) continue;
        $content = stream_get_contents($fh);
        fclose($fh);

        $json = json_decode($content, true);
        if (!is_array($json)) continue;

        $rows = (isset($json[0]) && is_array($json[0])) ? $json : [$json];
        foreach ($rows as $item) {
            $src = $this->detectSourceFromPath($file);
            $entity = $this->extractEntity($item);
            if (isset($item['user_id'])) {
                $userSiteEntities['User-' . $item['user_id']] = true;
            } elseif (isset($item['site_id'])) {
                $userSiteEntities['Site-' . $item['site_id']] = true;
            } elseif ($src) {
                $otherSources['SRC_' . $src] = ucfirst($src) . ' Files';
            } else {
                $hasUnassigned = true;
            }
        }
    }
    $filterGroups = [];
    if (count($userSiteEntities)) {
        $filterGroups[] = [ 'label' => 'User/Site Assignments', 'values' => array_keys($userSiteEntities) ];
    }
    if (count($otherSources)) {
        $filterGroups[] = [ 'label' => 'Other Data Sources', 'values' => array_keys($otherSources) ];
    }
    if ($hasUnassigned) {
        $filterGroups[] = [ 'label' => 'No Assignment', 'values' => [ '__UNASSIGNED__' ] ];
    }
    return [
        'groups' => $filterGroups,
        'sources' => $otherSources
    ];
}
 
  
  

    /**
     * Dashboard & Files Page
     */
    public function personaComplianceDashboard()
    {
        $jsonFiles = $this->getJsonFilesForUserConfigs();
        $allFiles = [];

        foreach ($jsonFiles as $jsonFilePath) {
    $content = @file_get_contents($jsonFilePath);
    if ($content === false) continue;
    $items = json_decode($content, true);

    if (is_array($items) && isset($items[0]) && is_array($items[0])) {
        foreach ($items as $item) {
            $parsed = $this->prepareFileAssessment($item, $jsonFilePath);
            $allFiles[] = $parsed;
        }
    } elseif (is_array($items)) {
        $allFiles[] = $this->prepareFileAssessment($items, $jsonFilePath);
    }
}

        // Dashboard Stats
        $highRisk = collect($allFiles)->where('compliance.overall_risk', 'HIGH')->count();
        $mediumRisk = collect($allFiles)->where('compliance.overall_risk', 'MEDIUM')->count();
        $lowRisk = collect($allFiles)->where('compliance.overall_risk', 'LOW')->count();
        $total = count($allFiles);
        $broadAccessFiles = $this->countBroadPermissionFiles($allFiles);

        return view('persona.dashboard', [
            'files' => $allFiles,
            'highRisk' => $highRisk,
            'mediumRisk' => $mediumRisk,
            'lowRisk' => $lowRisk,
            'total' => $total,
            'broadAccessFiles' => $broadAccessFiles
        ]);
    }

  

  
    /**
     * Prepare file with compliance & permissions parsed
     */
  
  	private function prepareFileAssessment($item, $jsonFilePath = '')
	{
    // Permissions may be missing, null, or not an array
    $permissions = [];
    if (isset($item['permissions']) && is_array($item['permissions'])) {
        $permissions = $item['permissions'];
    }
    // We use base64 encode for path, or sha1($jsonFilePath) for shorter (no special chars in URL).
    return [
        'file_name' => $item['file_name'] ?? '',
        'file_type' => $item['file_type'] ?? '',
        'last_modified' => $item['last_modified'] ?? '',
        'web_url' => $item['web_url'] ?? '',
        'size_bytes' => $item['size_bytes'] ?? '',
        'compliance' => $this->parseLlmResponse($item['llm_response'] ?? ''),
        'permissions' => $this->normalisePermissions($permissions),
        'fullpath_hash' => base64_encode($jsonFilePath), // or use sha1($jsonFilePath)
        'jsonFilePath' => $jsonFilePath // (optional, for debugging/internal use)
    ];
	}


    /** File detail view (compliance + permissions); robust to files missing permissions */
	public function personaFileDetail($hash, $fileName)
{
    $jsonFilePath = $this->urlsafe_b64decode($hash);
    $fileName = rawurldecode($fileName);

    \Log::info("Request decode: $hash => $jsonFilePath; fileName=$fileName");

    if (!file_exists($jsonFilePath)) {
        \Log::error("File does not exist: $jsonFilePath");
        abort(404, "File source not found.");
    }

    $content = @file_get_contents($jsonFilePath);
    if ($content === false) {
        \Log::error("Failed to read file: $jsonFilePath");
        abort(404, "Could not read file.");
    }

    $items = json_decode($content, true);

    if (is_array($items) && isset($items[0]) && is_array($items[0])) {
        foreach ($items as $item) {
            if (strcasecmp($item['file_name'] ?? '', $fileName) === 0) {
                return view('persona.file_detail', [
                    'file' => $this->prepareFileAssessment($item, $jsonFilePath)
                ]);
            }
        }
    } elseif (is_array($items) && strcasecmp($items['file_name'] ?? '', $fileName) === 0) {
        return view('persona.file_detail', [
            'file' => $this->prepareFileAssessment($items, $jsonFilePath)
        ]);
    }

    \Log::error("No matching file in $jsonFilePath for $fileName");
    abort(404, "Requested file not found in data set.");
}
  
  private function urlsafe_b64encode($string) {
    return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}
private function urlsafe_b64decode($string) {
    $pad = strlen($string) % 4;
    if ($pad > 0) $string .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($string, '-_', '+/'));
}
  
}