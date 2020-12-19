<?php namespace Mchuluq\Larv\Elfinder;

use Mchuluq\Larv\Elfinder\Session\LaravelSession;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory;
use League\Flysystem\Filesystem;

class ElfinderController extends Controller{
    protected $package = 'elfinder';

    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    public function __construct(Application $app){
        $this->app = $app;
    }

    public function showIndex(){
        return $this->app['view']
            ->make($this->package . '::elfinder')
            ->with($this->getViewVars());
    }

    public function showTinyMCE(){
        return $this->app['view']
            ->make($this->package . '::tinymce')
            ->with($this->getViewVars());
    }

    public function showTinyMCE4(){
        return $this->app['view']
            ->make($this->package . '::tinymce4')
            ->with($this->getViewVars());
    }

    public function showTinyMCE5(){
        return $this->app['view']
            ->make($this->package . '::tinymce5')
            ->with($this->getViewVars());
    }

    public function showCKeditor4(){
        return $this->app['view']
            ->make($this->package . '::ckeditor4')
            ->with($this->getViewVars());
    }

    public function showPopup($input_id){
        return $this->app['view']
            ->make($this->package . '::standalonepopup')
            ->with($this->getViewVars())
            ->with(compact('input_id'));
    }

    public function showFilePicker($input_id){
        $type = Request::input('type');
        $mimeTypes = implode(',',array_map(function($t){return "'".$t."'";}, explode(',',$type)));
        return $this->app['view']
            ->make($this->package . '::filepicker')
            ->with($this->getViewVars())
            ->with(compact('input_id','type','mimeTypes'));
    }

    public function showConnector()
    {
        $user = Auth::user();
        $roots = $this->app->config->get('elfinder.roots', []);
        if (empty($roots)) {
            $dirs = (array) $this->app['config']->get('elfinder.dir', []);
            foreach ($dirs as $dir) {
                $roots[] = [
                    'driver' => 'LocalFileSystem', // driver for accessing file system (REQUIRED)
                    'path' => public_path($dir), // path to files (REQUIRED)
                    'URL' => url($dir), // URL to files (REQUIRED)
                    'accessControl' => $this->app->config->get('elfinder.access') // filter callback (OPTIONAL)
                ];
            }

            $disks = (array) $this->app['config']->get('elfinder.disks', []);
            foreach ($disks as $key => $root) {
                if (is_string($root)) {
                    $key = $root;
                    $root = [];
                }
                $disk = app('filesystem')->disk($key);
                if ($disk instanceof FilesystemAdapter) {
                    $defaults = [
                        'driver' => 'Flysystem',
                        'filesystem' => null,
                        'alias' => $key,
                        'personal' => false
                    ];
                    $config = array_merge($defaults, $root);
                    // create personal folder
                    if($disk->getAdapter() instanceof \League\Flysystem\Adapter\Local){
                        if ($this->app['config']->get('elfinder.personal_folder', false) && $config['personal'] && Auth::check()) {
                            $new_path = $disk->getAdapter()->getPathPrefix() . md5($user->id);
                            if (!file_exists($new_path)) {
                                mkdir($new_path);
                            }
                            $disk->getAdapter()->setPathPrefix($new_path);
                            $config['URL'] = route('elfinder.get', ['disk' => Crypt::encryptString($key)]) . '/' . md5($user->id);
                        }
                    }
                    $config['filesystem'] = $disk->getDriver();
                    $roots[] = $config;
                }               
            }
        }

        if (app()->bound('session.store')) {
            $sessionStore = app('session.store');
            $session = new LaravelSession($sessionStore);
        } else {
            $session = null;
        }

        $rootOptions = $this->app->config->get('elfinder.root_options', array());
        foreach ($roots as $key => $root) {
           $roots[$key] = array_merge($rootOptions, $root);
        }

        $opts = $this->app->config->get('elfinder.options', array());
        $opts = array_merge($opts, ['roots' => $roots, 'session' => $session]);

        // run elFinder
        $connector = new Connector(new \elFinder($opts));
        $connector->run();
        return $connector->getResponse();
    }

    public function showFile(Request $req,$disk=null,$file_path=null){
        $disk = Crypt::decryptString($disk);
        if (Storage::disk($disk)->exists($file_path)) {
            return Storage::disk($disk)->response($file_path);
        } else {
            abort(404);
        }
    }

    protected function getViewVars(){
        $dir = 'assets/packages/mchuluq/larv/' . $this->package;
        $locale = str_replace("-",  "_", $this->app->config->get('app.locale'));
        if (!file_exists($this->app['path.public'] . "/$dir/js/i18n/elfinder.$locale.js")) {
            $locale = false;
        }
        $csrf = true;
        return compact('dir', 'locale', 'csrf');
    }
}
