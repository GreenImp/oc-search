<?php namespace GreenImp\Search\Components;

use Request;
use Cms\Classes\ComponentBase;

class SiteSearch extends ComponentBase{
  protected $results;

  public $search;
  public $ajax;
  public $terms;

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

  /**
   * Returns the class object for model search functionality
   *
   * @return mixed
   */
  protected function getModelSearch(){
    if(is_null($this->search)){
      $this->search = \App::make('search');
    }

    return $this->search;
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
    // TODO - categories results by type
    foreach($query->get() as $item){
      $results->push($item);
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
    $this->terms = $terms;
    $this->results = collect();

    // get the models
    $this->results = $this->results->merge($this->searchModels($terms));

    // TODO - field searches

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
    return $this->results;
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
