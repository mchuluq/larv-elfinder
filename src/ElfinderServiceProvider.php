<?php namespace Mchuluq\Larv\Elfinder;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ElfinderServiceProvider extends ServiceProvider {

    protected $defer = false;

    public function register(){
        $configPath = __DIR__ . '/../config/elfinder.php';
        $this->mergeConfigFrom($configPath, 'elfinder');
        $this->publishes([$configPath => config_path('elfinder.php')], 'config');
        $this->app->singleton('command.elfinder.publish', function($app){
			$publicPath = $app['path.public'];
            return new Console\PublishCommand($app['files'], $publicPath);
        });
        $this->commands('command.elfinder.publish');
	}

	public function boot(Router $router){
        $viewPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewPath, 'elfinder');
        $this->publishes([
            $viewPath => base_path('resources/views/vendor/elfinder'),
        ], 'views');

        if (!defined('ELFINDER_IMG_PARENT_URL')) {
			define('ELFINDER_IMG_PARENT_URL', $this->app['url']->asset('packages/mchuluq/larv/elfinder'));
		}

        $config = $this->app['config']->get('elfinder.route', []);
        $config['namespace'] = 'Mchuluq\Larv\Elfinder';

        $router->group($config, function($router)
        {
            $router->get('/',  ['as' => 'elfinder.index', 'uses' =>'ElfinderController@showIndex']);
            $router->any('connector', ['as' => 'elfinder.connector', 'uses' => 'ElfinderController@showConnector']);
            $router->any('get/{disk?}/{file_path?}', ['as' => 'elfinder.get', 'uses' => 'ElfinderController@showFile'])->where('file_path', '(.*)');
            $router->get('popup/{input_id}', ['as' => 'elfinder.popup', 'uses' => 'ElfinderController@showPopup']);
            $router->get('filepicker/{input_id}', ['as' => 'elfinder.filepicker', 'uses' => 'ElfinderController@showFilePicker']);
            //$router->get('tinymce', ['as' => 'elfinder.tinymce', 'uses' => 'ElfinderController@showTinyMCE']);
            //$router->get('tinymce4', ['as' => 'elfinder.tinymce4', 'uses' => 'ElfinderController@showTinyMCE4']);
            //$router->get('tinymce5', ['as' => 'elfinder.tinymce5', 'uses' => 'ElfinderController@showTinyMCE5']);
            //$router->get('ckeditor', ['as' => 'elfinder.ckeditor', 'uses' => 'ElfinderController@showCKeditor4']);
        });

        \Storage::extend('gdrive', function ($app, $config) {
            $settings = (Auth::check() && $config['personal']) ? Auth::user()->settings : [];

            $refreshToken = Arr::get($settings, 'gdrive.refreshToken',$config['refreshToken']);
            $folderId = (Auth::check() && $config['personal']) ? Arr::get($settings, 'gdrive.folderId') : $config['folderId'];

            if(!$refreshToken){
                return null;
            }

            $client = new \Google_Client();
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($refreshToken);
            $service = new \Google_Service_Drive($client);

            $options = [];
            if (isset($config['teamDriveId'])) {
                $options['teamDriveId'] = $config['teamDriveId'];
            }

            $adapter = new \Mchuluq\Larv\Elfinder\Adapter\GoogleDriveAdapter($service, $folderId, $options);
            return new \League\Flysystem\Filesystem($adapter);
        });
	}

	public function provides(){
		return array('command.elfinder.publish');
	}

}
