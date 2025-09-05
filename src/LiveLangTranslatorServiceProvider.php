<?php

namespace Ezmu\LiveLangTranslator;

use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use Ezmu\LiveLangTranslator\Translation\LiveLangTranslator;
use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Events\ResponseSending;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Http\Kernel;
class LiveLangTranslatorServiceProvider extends ServiceProvider
{
    public function register()
    {
            if (request()->has('LiveLang') && !defined('LL_RENDERED')) {
        $this->app->extend(Translator::class, function ($translator, $app) {
            return new LiveLangTranslator($translator->getLoader(), $translator->getLocale());
        });
          define('LL_RENDERED', true);
    }
    }


    public function boot()
    {
     
        Route::post('/dev/save-translation', function (Request $request) {
            $data = $request->all();
            $key = $data['key'] ?? null;
            if (!$key) {
                return response()->json(['message' => 'Key missing'], 400);
            }

            $supportedLocales = config('app.supported_locales', [app()->getLocale()]);

            foreach ($supportedLocales as $locale) {
                if (!isset($data[$locale])) continue;

                $translationsPath = resource_path("lang/{$locale}.json");

                $json = [];
                if (File::exists($translationsPath)) {
                    $json = json_decode(File::get($translationsPath), true);
                }

                $json[$key] = $data[$locale];
                File::put($translationsPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            return response()->json(['message' => 'Translation saved!']);
        })->middleware(['web']); 

    if (app()->runningInConsole()) {
        return;
    }

  
    if (request()->expectsJson()) {
        return;
    }

    
    if (!request()->has('LiveLang')) {
        return;
    }

    if (!defined('LL_FOOTER_RENDERED')) {
                $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(new class {
    protected static $injected = false;

    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if (self::$injected) {
            return $response; 
        }

        if ($response instanceof \Illuminate\Http\Response
            && stripos($response->headers->get('Content-Type'), 'text/html') !== false
            && $request->has('LiveLang')) {

            $scripts =  \LiveLangTranslator\Translation\LiveLangTranslator::renderFooterScripts();
            $content = $response->getContent();

            $content = str_ireplace('</body>', $scripts . '</body>', $content);
            $response->setContent($content);

         
        }

        return $response;
    }
});
        define('LL_FOOTER_RENDERED', true);
    }

    }
 
}