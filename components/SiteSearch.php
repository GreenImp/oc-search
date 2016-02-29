<?php namespace GreenImp\Search\Components;

use App;
use Request;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\ComponentBase;
use Symfony\Component\Finder\Finder;

class SiteSearch extends ComponentBase{
  protected $results;
  protected $fileSearch;
  protected $modelSearch;

  public $ajax;
  public $terms;

  /**
   * List of page URLs to ignore from searches
   *
   * @var array
   */
  public $urlIgnore = [
    '/',
    '/news/trade-shows',
    '/products',
    '/product-finder',
  ];

  public function componentDetails(){
    return [
      'name'        => 'Site Search',
      'description' => 'Outputs a search form and results in a CMS layout.'
    ];
  }

  public function defineProperties(){
    return [
      'ajax'  => [
        'title' => 'Use AJAX',
        'description' => 'Use AJAX for search form submission',
        'default' => false,
        'type'  => 'checkbox'
      ],
      'allowWildcards'  => [
        'title' => 'Partial matches',
        'description' => 'Search for partial word matches instead of only matching full words',
        'default' => true,
        'type' => 'checkbox',
        'group' => 'options'
      ]
    ];
  }

  public function onRun(){
    $this->ajax = $this->property('ajax', false);
  }

  protected function convertToResultItem($object){
    // TODO - this should use a trait on the Models
    // TODO - types need to be translatable
    $item = new \stdClass();

    switch(get_class($object)){
      case 'Cms\Classes\Page':
        $item->name = $object->title;
        $item->url = url($object->url);
        $item->type = 'Content';
        break;
      case 'GreenImp\TelcoProducts\Models\ProductGroup':
        $item->name = $object->name;
        $item->url = $object->url();
        $item->type = 'Series groups';
        break;
      case 'GreenImp\TelcoProducts\Models\Product':
        $item->name = $object->name;
        $item->url = $object->url();
        $item->type = 'Series';
        $item->excerpt = $object->description;
        break;
      case 'GreenImp\TelcoProducts\Models\ProductType':
        $item->name = $object->name;
        $item->url = $object->url();
        $item->type = 'Products';
        break;
      case 'JorgeAndrade\Events\Models\Event':
        $item->name = $object->name;
        $item->url = $object->slug;
        $item->excerpt = !empty($object->excerpt) ? $object->excerpt : $object->description;
        $item->type = 'Shows';
        break;
      default:
        if(isset($object->title) && !empty($object->title)){
          $item->name = $object->title;
        }elseif(isset($object->name) && !empty($object->name)){
          $item->name = $object->name;
        }else{
          $item->name = 'N/A';
        }

        if(isset($object->url)){
          $item->url = $object->url;
        }elseif(method_exists($object, 'url')){
          $item->url = $object->url();
        }else{
          $item->url = '';
        }

        $item->type = 'Other';

        $item->excerpt = isset($object->excerpt) ? $object->excerpt : null;
        break;
    }

    return $item;
  }

  /**
   * Returns the class object for file search functionality
   *
   * @return Finder
   */
  public function getFileSearch(){
    if(is_null($this->fileSearch)){
      $this->fileSearch = new Finder();
    }

    return $this->fileSearch;
  }

  public function searchPages($terms){
    $results = collect();

    // ensure terms is an array
    $terms = is_array($terms) ? $terms : array_filter(explode(' ', $terms));
    foreach($terms as $k => $term){
      $terms[$k] = strtolower($term);
    }

    // get the current theme
    $theme = Theme::getActiveTheme();

    $searchList = collect();


    /**
     * Get Static pages
     */

    // controller for converting static pages into pages
    $staticPageController = \RainLab\Pages\Classes\Controller::instance();
    // get the static page list class
    $pageList = new \RainLab\Pages\Classes\PageList($theme);

    // loop through each static page, get page object and add it to the parsing list
    $pages = $pageList->listPages();
    foreach($pages as $page){
      $searchList->push($staticPageController->initCmsPage($page->url));
    }


    /**
     * Get normal CMS pages
     */

    // get the normal pages
    $searchList = $searchList->merge($theme->listPages());



    // get the CMS controller for parsing pages
    $cms = new \Cms\Classes\Controller($theme);

    // loop through both static and normal pages and search their content
    foreach($searchList as $page){
      if($page->url == '/404'){
        // This is the 404 page - ignore it
        continue;
      }elseif(false !== strpos($page->url, ':')){
        // page has parameters - we can't realistically parse these successfully
        continue;
      }elseif(in_array($page->url, $this->urlIgnore)){
        // url is for a page that we're ignoring
        continue;
      }

      // set the layout to our custom blank one (This removes headers and footers so we don't search them
      $layout = $page->layout;
      $page->layout = ($layout == 'static-page-default') ? 'blank-static' : 'blank';

      // run the page and get the parsed content
      try{
        $pageContent = $cms->runPage($page, false);
      }catch(\Exception $e){
        continue;
      }

      // remove any HTMl as we dont' want to search that
      $pageContent = strip_tags($pageContent);

      // convert to lower case as we want case insensitive searching
      $pageContent = strtolower($pageContent);

      // search the content
      $isMatch = false;
      foreach($terms as $term){
        // TODO - use result of `substr_count` to determine sort order
        if(substr_count($pageContent, $term) > 0){
          // we have a match flag it and stop the loop
          $isMatch = true;
          break;
        }
      }

      if($isMatch){
        // this page matches - reset the layout and add it to the collection
        $page->layout = $layout;

        $results->push($this->convertToResultItem($page));
      }
    }

    return $results;
  }

  /**
   * Returns the class object for model search functionality
   *
   * @return mixed
   */
  protected function getModelSearch(){
    if(is_null($this->modelSearch)){
      $this->modelSearch = App::make('search');
    }

    return $this->modelSearch;
  }

  /**
   * Searches the models by the given terms
   *
   * @param array|string $terms
   * @return \Illuminate\Support\Collection
   */
  public function searchModels($terms){
    $results = collect();

    // parse the search terms and add wildcards to each term so we can do partial string matches
    $modelTerms = is_array($terms) ? $terms : explode(' ', $terms);
    if($this->property('allowWildcards', true)){
      foreach($modelTerms as $k => $term){
        // lucene only allows wildcards on terms greater than 2 characters
        if(strlen($term) > 2){
          $modelTerms[$k] = $term . '*';
        }
      }
    }

    // run the model search
    $query = $this->getModelSearch()->query(implode(' ', $modelTerms), '*', ['phrase' => false]);


    // loop through the results and add them to the list
    foreach($query->get() as $item){
      $object = $this->convertToResultItem($item);

      $results->push($object);
    }

    return $results;
  }

  /**
   * Runs a search with the given criteria
   * and returns the results
   *
   * @param array $terms
   * @return \Illuminate\Support\Collection
   */
  public function doSearch($terms){
    // save the search terms
    $this->terms = $terms;
    // create the results collection
    $this->results = collect();

    // get the models
    $this->results = $this->results->merge($this->searchModels($terms));

    // get the pages
    $this->results = $this->results->merge($this->searchPages($terms));

    // return the results
    return $this->results;
  }

  /**
   * Handles AJAX requests for searching
   *
   * @return mixed
   */
  public function onSearch(){
    // do the search
    $results = $this->doSearch(Request::input('terms'));

    if(Request::ajax()){
      // return the results as JSON
      return response()->json($results);
    }
  }

  /**
   * Returns the list of results
   *
   * @return \Illuminate\Support\Collection|null
   */
  public function results(){
    // TODO - order results by type, in this order
    // Products, Series groups, Series Ranges, Pages, News

    // return the results grouped by their type
    return !is_null($this->results) ? $this->results->groupBy('type') : null;
  }

  /**
   * Returns the number of results
   *
   * @return int
   */
  public function count(){
    return !is_null($this->results) ? $this->results->count() : 0;
  }
}
