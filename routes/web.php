<?php

// CHECK SYNTAX by runnign this in SSH: php -l routes/web.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;


//Route::post('/wizard/classify-database/{config_id}', [WizardController::class, 'classifyDatabase'])->name('wizard.classify-database');

//=================
// 1. App Controllers at the Top
//=================
use App\Http\Controllers\{
    DemoRequestController,
    CybersecVisualsController,
    WizardController,
    CybersecPolicyController,
    CybersecSIEMController,
    PlaygroundController,
    MathTutorController,
    PdfSummarizationController,
    WebBlogSummarizationController,
    OpenAIController,
    OpenAIAssistantController,
    CybereSecAiAgentsController
};

use App\Http\Controllers\AgenticAI\{
    InternalAuditorController,
    ComplianceAdvisorController,
    PolicyEnforcerController,
    DataBreachController,
    AiComplianceBotController,
    Compliance\EventController,
    DocsGeneratorController,
    AgentListController
};

use App\Http\Controllers\FilesSummary\FileSummariser;

//=================
// 2. Public Web Routes
//=================
Route::get('/demo-request', [DemoRequestController::class, 'showForm'])->name('demo.request.form');
Route::post('/demo-request', [DemoRequestController::class, 'submitRequest'])->name('demo.request');

use App\Http\Controllers\LoginController;

Route::get('/2fa', [LoginController::class, 'showTwoFactorForm'])->name('2fa.form');
Route::post('/2fa', [LoginController::class, 'verifyTwoFactor'])->name('2fa.verify');
Route::post('/2fa/resend', [LoginController::class, 'resendTwoFactor'])->name('2fa.resend');

// Fremium Demo (ai-bot-scan)
Route::post('/ai-bot-scan', [AiComplianceBotController::class, 'scan'])->name('ai.bot.scan');

// Blog
Route::get('/blog', fn () => view('blog.index'))->name('blog.index');
Route::get('/blog/{slug}', function ($slug) {
    $valid = ['ai-gdpr-2025', 'pitfalls-point-solutions', 'audit-trail-smb', 'gdpr-agentic-automation', 'data-breach-management-australia', 'why-cybersecai-is-the-modern-platform'];
    if (!in_array($slug, $valid)) abort(404);
    return view('blog.' . $slug);
})->name('blog.show');

// Static page
Route::view('/about', 'about')->name('about');



//=================
// 3. Authenticated Groups
//=================
Route::middleware(['auth'])->group(function () {
    // Wizard and Persona routes
    Route::controller(WizardController::class)->group(function () {
        Route::get('/wizard/step1',       'step1')->name('wizard.step1');
        Route::post('/wizard/step1',      'step1Post')->name('wizard.step1.post');
        Route::get('/wizard/step2',       'step2')->name('wizard.step2');
        Route::post('/wizard/step2',      'step2Post')->name('wizard.step2.post');
        Route::get('/wizard/step3',       'step3')->name('wizard.step3');
        Route::post('/wizard/step3',      'step3Post')->name('wizard.step3.post');
        Route::get('/wizard/step4',       'step4')->name('wizard.step4');
        Route::post('/wizard/step4',      'step4Post')->name('wizard.step4.post');
      	Route::match(['get', 'post'], '/wizard/privacy-regulations', 'privacyRegulations')->name('wizard.privacyRegulations');
      	Route::match(['get', 'post'], '/wizard/subject-categories', 'subjectCategories')->name('wizard.subjectCategories');	
      	Route::get('/wizard/step5',       'step5')->name('wizard.step5');
        Route::post('/wizard/step5',      'step5Post')->name('wizard.step5.post');
        Route::get('/wizard/done',        'done')->name('wizard.done');
        Route::get('/wizdashboard',       'dashboard')->name('wizard.dashboard');
        Route::get('/config/{id}',        'show')->name('wizard.show');
        Route::get('/config/{id}/edit',   'edit')->name('wizard.edit');
        Route::delete('/config/{id}',     'destroy')->name('wizard.destroy');
        Route::get('/persona/auditor',    'auditorPersonaDashboard')->name('persona.audit.dashboard');
        Route::get('/persona/risk',       'riskPersonaDashboard')->name('persona.risk.dashboard');
        Route::get('/persona/cybersecurity', 'cyberPersonaDashboard')->name('persona.cyber.dashboard');
        Route::get('/persona/dashboard',  'personaComplianceDashboard')->name('persona.dashboard');
        Route::get('/persona/file/{hash}/{fileName}', 'personaFileDetail')->name('persona.file_detail');
        // Add POST routes for classify/start
        Route::post('/wizard/classify-files-m365/{config_id}', 'classifyFilesM365');
        Route::post('/wizard/classify-files-smb/{config_id}', 'classifyFilesSMB');
        Route::post('/wizard/classify-files-nfs/{config_id}', 'classifyFilesNFS');
        Route::post('/wizard/classify-files-s3/{config_id}', 'classifyFilesS3');
        Route::post('/wizard/classify-database/{config_id}', 'classifyDatabase')->name('wizard.classify-database');
        //Route::post('/wizard/classify-database/{config_id}', [WizardController::class, 'classifyDatabase'])->name('wizard.classify-database');
        Route::post('/wizard/classify-files-gdrive/{config_id}', 'classifyFilesGDrive');
        Route::post('/wizard/start-classifying/{config_id}', 'startClassifying')->name('wizard.start_classifying');
        Route::post('/wizard/start-classifying-smb/{config_id}', 'startClassifyingSMB');
        Route::post('/wizard/start-classifying-nfs/{config_id}', 'startClassifyingNFS');
        Route::post('/wizard/start-classifying-s3/{config_id}', 'startClassifyingS3');
        Route::post('/wizard/start-classifying-gdrive/{config_id}', 'startClassifyingGDrive');
        Route::post('/wizard/start', 'startWizard')->name('wizard.start');
        //Route::get('/wizard/filesummary_pyramid', 'fileSummaryPyramid')->name('wizard.filesummary_pyramid');
        Route::get('/wizard/visuals-dashboard', fn () => view('wizard.visuals_dashboard'))->name('wizard.visuals_dashboard');
        //Route::get('/wizard/file-graph/network', 'fileGraphNetwork')->name('wizard.file_graph_network');
        //Route::get('/wizard/file-graph/table', 'fileGraphTable')->name('wizard.file_graph_table');
        Route::post('/wizard/establish-m365-link/{config_id}', 'establishM365Link')->name('wizard.establish_m365_link');
        
      
        //Route::get('/wizard/filesummary_pyramid', 'fileSummaryPyramid')->name('wizard.filesummary_pyramid');
        //Route::get('/wizard/file-graph/table', 'fileGraphTable')->name('wizard.file_graph_table');
      
        Route::get('/wizard/filesummary_pyramid', 'WizardController@fileSummaryPyramid')->name('wizard.filesummary_pyramid');
        Route::get('/wizard/file-graph/table', 'WizardController@fileGraphTable')->name('wizard.file_graph_table');
        Route::get('/wizard/file-graph/network', 'WizardController@fileGraphNetwork')->name('wizard.file_graph_network');
        Route::get('/api/pyramid-stats', 'WizardController@apiPyramidStats')->name('api.pyramid_stats');
        Route::get('/api/files-table', 'WizardController@apiFilesTable')->name('api.files_table');
    });

  //Route::get('/wizard/filesummary_pyramid', [WizardController::class, 'fileSummaryPyramid'])
    // ->name('wizard.filesummary_pyramid');
  
    //Route::get('/wizard/file-graph/table', [WizardController::class, 'fileGraphTable'])
    // ->name('wizard.file_graph_table');
  
    // Cybersec Visuals
    Route::get('/cybersecai-visuals', [CybersecVisualsController::class, 'index']);
    Route::get('/cybersecai-visuals/api/filedata', [CybersecVisualsController::class, 'apiFileData']);

    // Data Breach, Policies and SIEM
    Route::prefix('databreach/events')->group(function () {
        Route::get('/', [EventController::class, 'index'])->name('databreach.events.index');
        Route::get('/create', [EventController::class, 'create'])->name('databreach.events.create');
        Route::post('/store', [EventController::class, 'store'])->name('databreach.events.store');
        Route::get('/{id}', [EventController::class, 'show'])->name('databreach.events.show');
    });
    Route::prefix('cybersec-policy')->group(function () {
        Route::get('/{id}/edit', [CybersecPolicyController::class, 'edit'])->name('cybersec_policy.edit');
        Route::put('/{id}', [CybersecPolicyController::class, 'update'])->name('cybersec_policy.update');
    });
    Route::prefix('cybersecai_siem')->group(function () {
        Route::get('{id}/edit', [CybersecSIEMController::class, 'edit'])->name('cybersecai_siem.edit');
        Route::put('{id}', [CybersecSIEMController::class, 'update'])->name('cybersecai_siem.update');
        Route::post('{id}/sample', [CybersecSIEMController::class, 'sample'])->name('cybersecai_siem.sample');
        Route::post('{id}/test', [CybersecSIEMController::class, 'test'])->name('cybersecai_siem.test');
    });
    // Agentic AI
    Route::prefix('agentic_ai')->group(function () {
        Route::get('/auditor', [InternalAuditorController::class, 'index'])->name('agentic_ai.auditor');
        Route::post('/auditor/run', [InternalAuditorController::class, 'run'])->name('agentic_ai.auditor.run');
        Route::get('/compliance', [ComplianceAdvisorController::class, 'index'])->name('agentic_ai.compliance');
        Route::post('/compliance/run', [ComplianceAdvisorController::class, 'run'])->name('agentic_ai.compliance.run');
        Route::get('/policy', [PolicyEnforcerController::class, 'index'])->name('agentic_ai.policy');
        Route::post('/policy/run', [PolicyEnforcerController::class, 'run'])->name('agentic_ai.policy.run');
        Route::get('/agents', [AgentListController::class, 'index'])->name('agentic_ai.agents');
    });

    // AI Docs Generator
    Route::get('/agenticai/docs_agent/form', [DocsGeneratorController::class, 'showForm'])->name('agenticai.docs_agent.form');
    Route::post('/agenticai/docs_agent/generate', [DocsGeneratorController::class, 'generate'])->name('agenticai.docs_agent.generate');
    Route::get('/agenticai/docs_agent', [DocsGeneratorController::class, 'index'])->name('agenticai.docs_agent.index');
    Route::get('/agenticai/docs/json_download/{user_id}/{filename}', [DocsGeneratorController::class, 'jsonDownload'])->where('filename', '.*')->name('agenticai.docs.json_download');
    Route::get('/agenticai/docs/docx_download/{user_id}/{filename}', [DocsGeneratorController::class, 'docxDownload'])->where('filename', '.*')->name('agenticai.docs.docx_download');
    Route::post('/agenticai/docs_agent/delete', [DocsGeneratorController::class, 'deleteDocument'])->name('agenticai.docs_agent.delete');
});


Route::middleware(['auth'])->group(function() {
    Route::get('/agentic-ai/chatbot', [\App\Http\Controllers\AgenticAI\CybersecChatbotController::class, 'index'])->name('agentic.chatbot');
    Route::post('/agentic-ai/chatbot', [\App\Http\Controllers\AgenticAI\CybersecChatbotController::class, 'chat'])->name('agentic.chatbot.post');
});

Route::middleware(['auth'])->group(function() {
Route::get('/agentic-ai/chatorchestrator', [\App\Http\Controllers\AgenticAI\ChatOrchestratorController::class, 'view'])->name('chatorchestrator.view');
Route::post('/agentic-ai/chatorchestrator/post', [\App\Http\Controllers\AgenticAI\ChatOrchestratorController::class, 'orchestrate'])->name('chatorchestrator.orchestrate');
Route::get('/download_csv', [\App\Http\Controllers\AgenticAI\ChatOrchestratorController::class, 'downloadCsv'])
    ->name('download_csv');
  
  Route::get('/download_docx', [\App\Http\Controllers\AgenticAI\ChatOrchestratorController::class, 'downloadDocx'])
    ->name('download_docx');

});

Route::get('/chat/bootstrap', [\App\Http\Controllers\ChatWidgetController::class, 'bootstrap'])
    ->name('chatorchestrator.bootstrap')
    ->middleware('auth');

//Visuals Graphs etc...
Route::middleware(['auth'])->prefix('wizard')->name('wizard.')->group(function () {
// Existing pages
Route::get('/', [FileSummariser::class, 'index'])->name('index');
Route::get('/filesummary/pyramid', [FileSummariser::class, 'riskPyramid'])->name('filesummary_pyramid');
Route::get('/file-table', [FileSummariser::class, 'table'])->name('file_graph_table');
Route::get('/files', [FileSummariser::class, 'filesList'])->name('files.list');
Route::get('/file/{file}', [FileSummariser::class, 'fileDetail'])->name('file.show');

// New chart pages
Route::get('/filesummary/treemap', [FileSummariser::class, 'treemap'])->name('filesummary_treemap');
Route::get('/filesummary/sunburst', [FileSummariser::class, 'sunburst'])->name('filesummary_sunburst');
Route::get('/filesummary/stacked-bar', [FileSummariser::class, 'stackedBar'])->name('filesummary_stacked_bar');
Route::get('/filesummary/heatmap', [FileSummariser::class, 'heatmap'])->name('filesummary_heatmap');
Route::get('/filesummary/bubble', [FileSummariser::class, 'bubble'])->name('filesummary_bubble');
Route::get('/filesummary/sankey', [FileSummariser::class, 'sankey'])->name('filesummary_sankey');


});

Route::middleware(['auth'])->group(function () {
    Route::get('/filesummary/duplicates', [\App\Http\Controllers\FilesSummary\FileSummariser::class, 'duplicates'])->name('filesummary.duplicates');
    Route::get('/filesummary/duplicates.csv', [\App\Http\Controllers\FilesSummary\FileSummariser::class, 'duplicatesCsv'])->name('filesummary.duplicates.csv');
    Route::get('/filesummary/duplicates/group', [\App\Http\Controllers\FilesSummary\FileSummariser::class, 'duplicatesGroup'])->name('filesummary.duplicates.group');
    Route::get('/filesummary/duplicates/group.csv', [\App\Http\Controllers\FilesSummary\FileSummariser::class, 'duplicatesGroupCsv'])->name('filesummary.duplicates.group.csv');
    Route::get('/filesummary/files-list/partial', [FileSummariser::class, 'filesListPartial'])->name('filesummary.files_list_partial');
Route::get('/filesummary/file/{file}', [FileSummariser::class, 'fileDetail'])->name('filesummary.file_detail');
});

// CyberSec AI Agents
Route::middleware(['auth'])->group(function () {
    Route::get('/cybersecaiagents', [CybereSecAiAgentsController::class, 'welcome'])->name('cybersecaiagents.welcome');
    Route::get('/cybersecaiagents/step1', [CybereSecAiAgentsController::class, 'step1'])->name('cybersecaiagents.step1');
    Route::post('/cybersecaiagents/step1', [CybereSecAiAgentsController::class, 'step1Post'])->name('cybersecaiagents.step1Post');
    Route::get('/cybersecaiagents/policy', [CybereSecAiAgentsController::class, 'policyForm'])->name('cybersecaiagents.policyForm');
    Route::post('/cybersecaiagents/policy', [CybereSecAiAgentsController::class, 'policySubmit'])->name('cybersecaiagents.policySubmit');
    Route::match(['get', 'post'],'/cybersecaiagents/agentstep', [CybereSecAiAgentsController::class, 'agentStep'])->name('cybersecaiagents.agentStep');
    Route::get('/cybersecaiagents/discover', [CybereSecAiAgentsController::class, 'discovery'])->name('cybersecaiagents.discover');
    Route::get('/cybersecaiagents/classify', [CybereSecAiAgentsController::class, 'classification'])->name('cybersecaiagents.classify');
    Route::get('/cybersecaiagents/visuals', [CybereSecAiAgentsController::class, 'visualsDashboard'])->name('cybersecaiagents.visualsDashboard');
    Route::post('/cybersecaiagents/agentchat', [CybereSecAiAgentsController::class, 'agentChat'])->name('cybersecaiagents.agentChat');
});


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('enable-debugger', 'HomeController@activateDebugger');

Route::match(array('GET', 'POST'),'create-users-wallet', 'HomeController@walletUser');



//cron job

Route::get('cron/ical-synchronization','CronController@iCalendarSynchronization');

//user can view it anytime with or without logged in
Route::group(['middleware' => ['locale']], function () {
    Route::get('/', 'HomeController@index');
    Route::post('search/result', 'SearchController@searchResult');
    //Route::post('search/result', 'SearchController@searchResult3');
    //Route::post('search/result', 'SearchController@index');
    Route::get('search', 'SearchController@index');
    Route::match(array('GET', 'POST'),'properties/{slug}', 'PropertyController@single')->name('property.single');
    Route::match(array('GET', 'POST'),'property/get-price', 'PropertyController@getPrice');
    Route::get('set-slug/', 'PropertyController@set_slug');
    Route::get('signup', 'LoginController@signup');
    Route::post('/checkUser/check', 'LoginController@check')->name('checkUser.check');
});

//Auth::routes();

Route::post('set_session', 'HomeController@setSession');

//only can view if admin is logged in
Route::group(['prefix' => 'admin', 'namespace' => 'Admin', 'middleware' => ['guest:admin']], function(){
    Route::get('/', function(){
        return Redirect::to('admin/dashboard');
    });

    Route::match(array('GET', 'POST'), 'profile', 'AdminController@profile');
    Route::get('logout', 'AdminController@logout');
    Route::get('dashboard', 'DashboardController@index');


    Route::post('users/approve/{user}', 'CustomerController@approveUser')->name('admin.users.approve');
    Route::get('users/role/{user}', 'CustomerController@editUserRole')->name('admin.users.editRole');
    Route::post('users/role/{user}', 'CustomerController@updateUserRole')->name('admin.users.updateRole');

    Route::post('users/approve/{id}', 'UserController@approve')->name('admin.users.approve');
    Route::get('customers', 'CustomerController@index')->middleware(['permission:customers']);
    Route::get('customers/customer_search', 'CustomerController@searchCustomer')->middleware(['permission:customers']);
    Route::post('add-ajax-customer', 'CustomerController@ajaxCustomerAdd')->middleware(['permission:add_customer']);
    Route::match(array('GET', 'POST'), 'add-customer', 'CustomerController@add')->middleware(['permission:add_customer']);

    Route::group(['middleware' => 'permission:edit_customer'], function () {
        Route::match(array('GET', 'POST'), 'edit-customer/{id}', 'CustomerController@update');
        Route::get('customer/properties/{id}', 'CustomerController@customerProperties');
        Route::get('customer/bookings/{id}', 'CustomerController@customerBookings');
        Route::post('customer/bookings/property_search', 'BookingsController@searchProperty');
        Route::get('customer/payouts/{id}', 'CustomerController@customerPayouts');
        Route::get('customer/payment-methods/{id}', 'CustomerController@paymentMethods');
        Route::get('customer/wallet/{id}', 'CustomerController@customerWallet');

        Route::get('customer/properties/{id}/property_list_csv', 'PropertiesController@propertyCsv');
        Route::get('customer/properties/{id}/property_list_pdf', 'PropertiesController@propertyPdf');

        Route::get('customer/bookings/{id}/booking_list_csv', 'BookingsController@bookingCsv');
        Route::get('customer/bookings/{id}/booking_list_pdf', 'BookingsController@bookingPdf');

        Route::get('customer/payouts/{id}/payouts_list_pdf', 'PayoutsController@payoutsPdf');
        Route::get('customer/payouts/{id}/payouts_list_csv', 'PayoutsController@payoutsCsv');

        Route::get('customer/customer_list_csv', 'CustomerController@customerCsv');
        Route::get('customer/customer_list_pdf', 'CustomerController@customerPdf');
    });

    Route::post('delete-customer', 'CustomerController@delete')->name('admin.customers.delete');

    Route::group(['middleware' => 'permission:manage_messages'], function () {
        Route::get('messages', 'AdminController@customerMessage');
        Route::match(array('GET', 'POST'), 'delete-message/{id}', 'AdminController@deleteMessage');
        Route::match(array('GET','POST'), 'send-message-email/{id}', 'AdminController@sendEmail');
        Route::match(['get', 'post'],'upload_image','AdminController@uploadImage')->name('upload');
        Route::get('messaging/host/{id}', 'AdminController@hostMessage');
        Route::post('reply/{id}', 'AdminController@reply');
    });

    Route::get('properties', 'PropertiesController@index')->middleware(['permission:properties']);
    Route::match(array('GET', 'POST'), 'add-properties', 'PropertiesController@add')->middleware(['permission:add_properties']);
    Route::get('properties/property_list_csv', 'PropertiesController@propertyCsv');
    Route::get('properties/property_list_pdf', 'PropertiesController@propertyPdf');

    Route::group(['middleware' => 'permission:edit_properties'], function () {
        Route::match(array('GET', 'POST'),'listing/{id}/photo_message', 'PropertiesController@photoMessage');
        Route::match(array('GET', 'POST'),'listing/{id}/photo_delete', 'PropertiesController@photoDelete');
        Route::match(array('GET', 'POST'),'listing/{id}/update_status', 'PropertiesController@update_status');
        Route::match(array('POST'),'listing/photo/make_default_photo', 'PropertiesController@makeDefaultPhoto');
        Route::match(array('POST'),'listing/photo/make_photo_serial', 'PropertiesController@makePhotoSerial');
        Route::match(array('GET', 'POST'),'listing/{id}/{step}', 'PropertiesController@listing')->where(['id' => '[0-9]+','page' => 'basics|description|location|amenities|photos|pricing|calendar|details|booking']);
    });

    Route::post('ajax-calender/{id}', 'CalendarController@calenderJson');
    Route::post('ajax-calender-price/{id}', 'CalendarController@calenderPriceSet');
    //iCalender routes for admin
    Route::post('ajax-icalender-import/{id}', 'CalendarController@icalendarImport');
    Route::get('icalendar/synchronization/{id}', 'CalendarController@icalendarSynchronization');
    //iCalender routes end
    Route::match(array('GET', 'POST'), 'edit_property/{id}', 'PropertiesController@update')->middleware(['permission:edit_properties']);
    Route::get('delete-property/{id}', 'PropertiesController@delete')->middleware(['permission:delete_property']);
    Route::get('bookings', 'BookingsController@index')->middleware(['permission:manage_bookings']);
    Route::get('bookings/property_search', 'BookingsController@searchProperty')->middleware(['permission:manage_bookings']);
    Route::get('bookings/customer_search', 'BookingsController@searchCustomer')->middleware(['permission:manage_bookings']);
    //booking details
    Route::get('bookings/detail/{id}', 'BookingsController@details')->middleware(['permission:manage_bookings']);
    Route::get('bookings/edit/{req}/{id}', 'BookingsController@updateBookingStatus')->middleware(['permission:manage_bookings']);
    Route::post('bookings/pay', 'BookingsController@pay')->middleware(['permission:manage_bookings']);
    Route::get('booking/need_pay_account/{id}/{type}', 'BookingsController@needPayAccount');
    Route::get('booking/booking_list_csv', 'BookingsController@bookingCsv');
    Route::get('booking/booking_list_pdf', 'BookingsController@bookingPdf');
    Route::get('payouts', 'PayoutsController@index')->middleware(['permission:view_payouts']);
    Route::match(array('GET', 'POST'), 'payouts/edit/{id}', 'PayoutsController@edit');
    Route::get('payouts/details/{id}', 'PayoutsController@details');
    Route::get('payouts/payouts_list_pdf', 'PayoutsController@payoutsPdf');
    Route::get('payouts/payouts_list_csv', 'PayoutsController@payoutsCsv');
    Route::group(['middleware' => 'permission:manage_reviews'], function () {
        Route::get('reviews', 'ReviewsController@index');
        Route::match(array('GET', 'POST'), 'edit_review/{id}', 'ReviewsController@edit');
        Route::get('reviews/review_search', 'ReviewsController@searchReview');
        Route::get('reviews/review_list_csv', 'ReviewsController@reviewCsv');
        Route::get('reviews/review_list_pdf', 'ReviewsController@reviewPdf');

    });

    // Route::get('reports', 'ReportsController@index')->middleware(['permission:manage_reports']);

    // For Reporting
    Route::group(['middleware' => 'permission:view_reports'], function () {
        Route::get('sales-report', 'ReportsController@salesReports');
        Route::get('sales-analysis', 'ReportsController@salesAnalysis');
        Route::get('reports/property-search', 'ReportsController@searchProperty');
        Route::get('overview-stats', 'ReportsController@overviewStats');
    });

    Route::group(['middleware' => 'permission:manage_amenities'], function () {
        Route::get('amenities', 'AmenitiesController@index');
        Route::match(array('GET', 'POST'), 'add-amenities', 'AmenitiesController@add');
        Route::match(array('GET', 'POST'), 'edit-amenities/{id}', 'AmenitiesController@update');
        Route::get('delete-amenities/{id}', 'AmenitiesController@delete');
    });

    Route::group(['middleware' => 'permission:manage_pages'], function () {
        Route::get('pages', 'PagesController@index');
        Route::match(array('GET', 'POST'), 'add-page', 'PagesController@add');
        Route::match(array('GET', 'POST'), 'edit-page/{id}', 'PagesController@update');
        Route::get('delete-page/{id}', 'PagesController@delete');

    });


    Route::group(['middleware' => 'permission:manage_admin'], function () {
        Route::get('admin-users', 'AdminController@index');
        Route::match(array('GET', 'POST'), 'add-admin', 'AdminController@add');
        Route::match(array('GET', 'POST'), 'edit-admin/{id}', 'AdminController@update');
        Route::match(array('GET', 'POST'), 'delete-admin/{id}', 'AdminController@delete');
    });

    Route::group(['middleware' => 'permission:general_setting'], function () {
        Route::match(array('GET', 'POST'), 'settings', 'SettingsController@general')->middleware(['permission:general_setting']);
        Route::match(array('GET', 'POST'), 'settings/preferences', 'SettingsController@preferences')->middleware(['permission:preference']);
        Route::post('settings/delete_logo', 'SettingsController@deleteLogo');
        Route::post('settings/delete_favicon', 'SettingsController@deleteFavIcon');
        Route::match(array('GET', 'POST'), 'settings/fees', 'SettingsController@fees')->middleware(['permission:manage_fees']);
        Route::group(['middleware' => 'permission:manage_banners'], function () {
            Route::get('settings/banners', 'BannersController@index');
            Route::match(array('GET', 'POST'), 'settings/add-banners', 'BannersController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-banners/{id}', 'BannersController@update');
            Route::get('settings/delete-banners/{id}', 'BannersController@delete');
        });

        Route::group(['middleware' => 'permission:starting_cities_settings'], function () {
            Route::get('settings/starting-cities', 'StartingCitiesController@index');
            Route::match(array('GET', 'POST'), 'settings/add-starting-cities', 'StartingCitiesController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-starting-cities/{id}', 'StartingCitiesController@update');
            Route::get('settings/delete-starting-cities/{id}', 'StartingCitiesController@delete');
        });

        Route::group(['middleware' => 'permission:manage_property_type'], function () {
            Route::get('settings/property-type', 'PropertyTypeController@index');
            Route::match(array('GET', 'POST'), 'settings/add-property-type', 'PropertyTypeController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-property-type/{id}', 'PropertyTypeController@update');
            Route::get('settings/delete-property-type/{id}', 'PropertyTypeController@delete');
        });

        Route::group(['middleware' => 'permission:space_type_setting'], function () {
            Route::get('settings/space-type', 'SpaceTypeController@index');
            Route::match(array('GET', 'POST'), 'settings/add-space-type', 'SpaceTypeController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-space-type/{id}', 'SpaceTypeController@update');
            Route::get('settings/delete-space-type/{id}', 'SpaceTypeController@delete');
        });

        Route::group(['middleware' => 'permission:manage_bed_type'], function () {
            Route::get('settings/bed-type', 'BedTypeController@index');
            Route::match(array('GET', 'POST'), 'settings/add-bed-type', 'BedTypeController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-bed-type/{id}', 'BedTypeController@update');
            Route::get('settings/delete-bed-type/{id}', 'BedTypeController@delete');
        });

        Route::group(['middleware' => 'permission:manage_respite_type'], function () {
            Route::get('settings/respite-type', 'RespiteTypeController@index');
            Route::match(array('GET', 'POST'), 'settings/add-respite-type', 'RespiteTypeController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-respite-type/{id}', 'RespiteTypeController@update');
            Route::get('settings/delete-respite-type/{id}', 'RespiteTypeController@delete');
        });

        Route::group(['middleware' => 'permission:manage_currency'], function () {
            Route::get('settings/currency', 'CurrencyController@index');
            Route::match(array('GET', 'POST'), 'settings/add-currency', 'CurrencyController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-currency/{id}', 'CurrencyController@update');
            Route::get('settings/delete-currency/{id}', 'CurrencyController@delete');
        });

        Route::group(['middleware' => 'permission:manage_country'], function () {
            Route::get('settings/country', 'CountryController@index');
            Route::match(array('GET', 'POST'), 'settings/add-country', 'CountryController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-country/{id}', 'CountryController@update');
            Route::get('settings/delete-country/{id}', 'CountryController@delete');
        });

        Route::group(['middleware' => 'permission:manage_amenities_type'], function () {
            Route::get('settings/amenities-type', 'AmenitiesTypeController@index');
            Route::match(array('GET', 'POST'), 'settings/add-amenities-type', 'AmenitiesTypeController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-amenities-type/{id}', 'AmenitiesTypeController@update');
            Route::get('settings/delete-amenities-type/{id}', 'AmenitiesTypeController@delete');
        });

        Route::match(array('GET', 'POST'), 'settings/email', 'SettingsController@email')->middleware(['permission:email_settings']);



        Route::group(['middleware' => 'permission:manage_language'], function () {
            Route::get('settings/language', 'LanguageController@index');
            Route::match(array('GET', 'POST'), 'settings/add-language', 'LanguageController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-language/{id}', 'LanguageController@update');
            Route::get('settings/delete-language/{id}', 'LanguageController@delete');
        });

        Route::match(array('GET', 'POST'), 'settings/fees', 'SettingsController@fees')->middleware(['permission:manage_fees']);

        Route::group(['middleware' => 'permission:manage_metas'], function () {
            Route::get('settings/metas', 'MetasController@index');
            Route::match(array('GET', 'POST'), 'settings/edit_meta/{id}', 'MetasController@update');
        });

        Route::match(array('GET', 'POST'), 'settings/api-informations', 'SettingsController@apiInformations')->middleware(['permission:api_informations']);
        Route::match(array('GET', 'POST'), 'settings/payment-methods', 'SettingsController@paymentMethods')->middleware(['permission:payment_settings']);
        Route::match(array('GET', 'POST'), 'settings/bank/add', 'BankController@addBank')->middleware(['permission:payment_settings']);
        Route::match(array('GET', 'POST'), 'settings/bank/edit/{bank}', 'BankController@editBank')->middleware(['permission:payment_settings']);
        Route::get('settings/bank/{bank}', 'BankController@show')->middleware(['permission:payment_settings']);
        Route::get('settings/bank/delete/{bank}', 'BankController@deleteBank')->middleware(['permission:payment_settings']);
        Route::match(array('GET', 'POST'), 'settings/social-links', 'SettingsController@socialLinks')->middleware(['permission:social_links']);

        Route::match(array('GET', 'POST'), 'settings/social-logins', 'SettingsController@socialLogin')->middleware(['permission:social_logins']);;

        Route::group(['middleware' => 'permission:manage_roles'], function () {
            Route::get('settings/roles', 'RolesController@index');
            Route::match(array('GET', 'POST'), 'settings/add-role', 'RolesController@add');
            Route::match(array('GET', 'POST'), 'settings/edit-role/{id}', 'RolesController@update');
            Route::get('settings/delete-role/{id}', 'RolesController@delete');
        });

        Route::group(['middleware' => 'permission:database_backup'], function () {
            Route::get('settings/backup', 'BackupController@index');
            Route::get('backup/save', 'BackupController@add');
            Route::get('backup/download/{id}', 'BackupController@download');
        });

        Route::group(['middleware' => 'permission:manage_email_template'], function () {
            Route::get('email-template/{id}', 'EmailTemplateController@index');
            Route::post('email-template/{id}','EmailTemplateController@update');
        });

        Route::group(['middleware' => 'permission:manage_testimonial'], function () {
            Route::get('testimonials', 'TestimonialController@index');
            Route::match(array('GET', 'POST'), 'add-testimonials', 'TestimonialController@add');
            Route::match(array('GET', 'POST'), 'edit-testimonials/{id}', 'TestimonialController@update');
            Route::get('delete-testimonials/{id}', 'TestimonialController@delete');
        });
    });
});

//only can view if admin is not logged in if they are logged in then they will be redirect to dashboard
Route::group(['prefix' => 'admin', 'namespace' => 'Admin', 'middleware' => 'no_auth:admin'], function () {
    Route::get('login', 'AdminController@login');
});

//only can view if user is not logged in if they are logged in then they will be redirect to dashboard
Route::group(['middleware' => ['no_auth:users', 'locale']], function () {
    //Route::get('login', 'LoginController@index');
    Route::get('login', 'LoginController@index')->name('login');
    Route::get('auth/login', function()
    {
        return Redirect::to('login');
    });

    Route::get('googleLogin', 'LoginController@googleLogin')->middleware('social_login:google_login');
    Route::get('facebookLogin', 'LoginController@facebookLogin')->middleware('social_login:facebook_login');
    Route::get('register', 'HomeController@register');
    Route::match(array('GET', 'POST'), 'forgot_password', 'LoginController@forgotPassword');
    Route::post('create', 'UserController@create');
    Route::post('authenticate', 'LoginController@authenticate');
  
  	// For viewing the reset password form
	Route::get('reset_password/{token}', 'LoginController@resetPassword')->name('reset_password.form');

	// For submitting the password reset form
	Route::post('reset_password/{token}', 'LoginController@resetPassword')->name('reset_password.submit');
    //Route::match(array('GET', 'POST'), 'reset_password/{token}', 'LoginController@resetPassword')->name('reset_password.token');
    //Route::get('users/reset_password/{secret?}', 'LoginController@resetPassword');
    //Route::post('users/reset_password', 'LoginController@resetPassword');
});

Route::get('googleAuthenticate', 'LoginController@googleAuthenticate');
Route::get('facebookAuthenticate', 'LoginController@facebookAuthenticate');

Route::get('users/confirm_email/{code?}', 'UserController@confirmEmail');

//only can view if user is logged in
Route::group(['middleware' => ['guest:users', 'locale']], function () {
    //Route::get('dashboard', 'UserController@dashboard');
    Route::get('dashboard', 'UserController@dashboard')->name('users.dashboard');
    Route::post('update-configs', 'UserController@updateConfigs')->name('user.updateConfigs');
    Route::get('exec_dashboard', 'UserController@execdashboard');
    Route::match(array('GET', 'POST'),'users/profile', 'UserController@profile');
    Route::match(array('GET', 'POST'),'users/profile/media', 'UserController@media');

    // User verification

    //Route::get('users/confirm_email/{code?}', 'UserController@confirmEmail');
    Route::get('users/edit-verification', 'UserController@verification');
    Route::get('users/new_email_confirm', 'UserController@newConfirmEmail');

    Route::get('facebookLoginVerification', 'UserController@facebookLoginVerification');
    Route::get('facebookConnect/{id}', 'UserController@facebookConnect');
    Route::get('facebookDisconnect', 'UserController@facebookDisconnectVerification');

    Route::get('googleLoginVerification', 'UserController@googleLoginVerification');
    Route::get('googleConnect/{id}', 'UserController@googleConnect');
    Route::get('googleDisconnect', 'UserController@googleDisconnect');
    // Route::get('googleAuthenticate', 'LoginController@googleAuthenticate');

    Route::get('users/show/{id}', 'UserController@show');
    Route::match(array('GET', 'POST'),'users/reviews', 'UserController@reviews');
    Route::match(array('GET', 'POST'),'users/reviews_by_you', 'UserController@reviewsByYou');
    Route::match(['get', 'post'], 'reviews/edit/{id}', 'UserController@editReviews');
    Route::match(['get', 'post'], 'reviews/details', 'UserController@reviewDetails');

    Route::match(array('GET', 'POST'),'properties', 'PropertyController@userProperties');
    Route::match(array('GET', 'POST'),'facilities', 'PropertyController@userFacilities');
    Route::post('facilities/create_booking', 'PropertyController@createBooking');
    Route::get('facilities/stripe', 'PropertyController@stripePayment');
    Route::post('facilities/stripe-request', 'PropertyController@stripeRequest');
    Route::match(['get', 'post'], 'facilities/bank-payment', 'PropertyController@bankPayment');
    Route::match(array('GET', 'POST'),'property/create', 'PropertyController@create');
    Route::match(array('GET', 'POST'),'listing/{id}/photo_message', 'PropertyController@photoMessage')->middleware(['checkUserRoutesPermissions']);
    Route::match(array('GET', 'POST'),'listing/{id}/photo_delete', 'PropertyController@photoDelete')->middleware(['checkUserRoutesPermissions']);

    Route::match(array('POST'),'listing/photo/make_default_photo', 'PropertyController@makeDefaultPhoto');

    Route::match(array('POST'),'listing/photo/make_photo_serial', 'PropertyController@makePhotoSerial');

    Route::match(array('GET', 'POST'),'listing/update_status', 'PropertyController@updateStatus');
    Route::match(array('GET', 'POST'),'listing/{id}/{step}', 'PropertyController@listing')->where(['id' => '[0-9]+','page' => 'basics|description|location|amenities|photos|pricing|calendar|details|booking']);

    // Favourites routes
    Route::get('user/favourite', 'PropertyController@userBookmark');
    Route::post('add-edit-book-mark', 'PropertyController@addEditBookMark');

    Route::post('ajax-calender/{id}', 'CalendarController@calenderJson');
    Route::post('ajax-calender-price/{id}', 'CalendarController@calenderPriceSet');
    //iCalendar routes start
    Route::post('ajax-icalender-import/{id}', 'CalendarController@icalendarImport');
    Route::get('icalendar/synchronization/{id}', 'CalendarController@icalendarSynchronization');
    //iCalendar routes end
    Route::post('currency-symbol', 'PropertyController@currencySymbol');
    Route::match(['get', 'post'], 'payments/book/{id?}', 'PaymentController@index');
    Route::post('payments/create_booking', 'PaymentController@createBooking');
    Route::get('payments/success', 'PaymentController@success');
    Route::get('payments/cancel', 'PaymentController@cancel');
    Route::get('payments/stripe', 'PaymentController@stripePayment');
    Route::post('payments/stripe-request', 'PaymentController@stripeRequest');
    Route::match(['get', 'post'], 'payments/bank-payment', 'PaymentController@bankPayment');
    Route::get('booking/{id}', 'BookingController@index')->where('id', '[0-9]+');
    Route::get('booking_payment/{id}', 'BookingController@requestPayment')->where('id', '[0-9]+');
    Route::get('booking/requested', 'BookingController@requested');
    Route::get('booking/itinerary_friends', 'BookingController@requested');
    Route::post('booking/accept/{id}', 'BookingController@accept');
    Route::post('booking/decline/{id}', 'BookingController@decline');
    Route::get('booking/expire/{id}', 'BookingController@expire');
    Route::match(['get', 'post'], 'my-bookings', 'BookingController@myBookings');
    Route::post('booking/host_cancel', 'BookingController@hostCancel');
    Route::match(['get', 'post'], 'trips/active', 'TripsController@myTrips');
    Route::get('booking/receipt', 'TripsController@receipt');
    Route::post('trips/guest_cancel', 'TripsController@guestCancel');

    // Messaging
    Route::match(['get', 'post'], 'inbox', 'InboxController@index');
    Route::post('messaging/booking/', 'InboxController@message');
    Route::post('messaging/reply/', 'InboxController@messageReply');

    Route::match(['get', 'post'], 'users/account-preferences', 'UserController@accountPreferences');
    Route::get('users/account_delete/{id}', 'UserController@accountDelete');
    Route::get('users/account_default/{id}', 'UserController@accountDefault');
    Route::get('users/transaction-history', 'UserController@transactionHistory');
    Route::post('users/account_transaction_history', 'UserController@getCompletedTransaction');
    // for customer payout settings
    Route::match(['GET', 'POST'], 'users/payout', 'PayoutController@index');
    Route::match(['GET', 'POST'], 'users/payout/setting', 'PayoutController@setting');
    Route::match(['GET', 'POST'], 'users/payout/edit-payout/', 'PayoutController@edit');
    Route::match(['GET', 'POST'], 'users/payout/delete-payout/{id}', 'PayoutController@delete');

    // for payout request
    Route::match(['GET', 'POST'], 'users/payout-list', 'PayoutController@payoutList');
    Route::match(['GET', 'POST'], 'users/payout/success', 'PayoutController@success');

    Route::match(['get', 'post'], 'users/security', 'UserController@security');
    Route::get('logout', function()
    {
        Auth::logout();
        Session::flush();
        return Redirect::to('login');
    });
});

//for exporting iCalendar
Route::get('icalender/export/{id}', 'CalendarController@icalendarExport');
Route::post('admin/authenticate', 'Admin\AdminController@authenticate');
Route::get('{name}', 'HomeController@staticPages');
Route::post('duplicate-phone-number-check', 'UserController@duplicatePhoneNumberCheck');
Route::post('duplicate-phone-number-check-for-existing-customer', 'UserController@duplicatePhoneNumberCheckForExistingCustomer');
Route::match(['GET', 'POST'], 'admin/settings/sms', 'Admin\SettingsController@smsSettings');
Route::match(['get', 'post'],'upload_image','Admin\PagesController@uploadImage')->name('upload');
