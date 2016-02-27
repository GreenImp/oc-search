<?php namespace GreenImp\Search;

use App;
use Event;
use Illuminate\Foundation\AliasLoader;
use Backend\Facades\Backend;
use System\Classes\PluginBase;

/**
 * Search Plugin Information File
 */
class Plugin extends PluginBase
{
  /**
   * Returns information about this plugin.
   *
   * @return array
   */
  public function pluginDetails(){
    return [
      'name'        => 'greenimp.search::lang.app.name',
      'description' => 'greenimp.search::lang.app.description',
      'author'      => 'GreenImp',
      'icon'        => 'icon-search'
    ];
  }

  public function registerComponents(){
    return [
      'GreenImp\Search\Components\SiteSearch' => 'siteSearch'
    ];
  }

  public function boot(){
    App::register('Nqxcode\LuceneSearch\ServiceProvider');

    // Register aliases
    $alias = AliasLoader::getInstance();
    $alias->alias('Search', 'Nqxcode\LuceneSearch\Facade');


    Event::listen('backend.form.extendFields', function($widget){
      if (
        !$widget->getController() instanceof \RainLab\Pages\Controllers\Index ||
        !$widget->model instanceof \RainLab\Pages\Classes\MenuItem
      ){
        return;
      }

      $widget->addTabFields([
        'viewBag[extraAttributes]' => [
          'tab' => 'Attributes',
          'label' => 'Extra',
          'comment' => 'Enter any extra attrbutes that the menu item should have (ie. `data` attributes)',
          'type' => 'text'
        ]
      ]);
    });
  }
}
