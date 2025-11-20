namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle($request, Closure $next)
    {
        if (!Auth::guard('admin')->check()) {
            return redirect('/admin/login')->with('message', 'Admin login required')->with('alert-class', 'alert-danger');
        }
        return $next($request);
    }
}