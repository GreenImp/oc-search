<?php namespace GreenImp\Search;

use Event;
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

  public function registerComponents(){}
}
