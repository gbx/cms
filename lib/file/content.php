<?php 

namespace Kirby\CMS\File;

use Kirby\Toolkit\A;
use Kirby\Toolkit\C;
use Kirby\Toolkit\F;
use Kirby\Toolkit\Str;
use Kirby\CMS\Site;
use Kirby\CMS\File;
use Kirby\CMS\Language;
use Kirby\CMS\Variable;

// direct access protection
if(!defined('KIRBY')) die('Direct access is not allowed');

/**
 * Content
 * 
 * The content object is an extended File
 * object which is used for all content text files. 
 * 
 * Content objects are used for the main content, site info and 
 * meta information for other files. 
 * 
 * Page objects access their main content object
 * to return custom field data. 
 * 
 * i.e. $page->title() is the same as $page->content()->title()
 * 
 * Content objects have many child Variable objects
 * for each parsed field in the text file. 
 * So all custom field contents are Variable objects. 
 * 
 * @package   Kirby CMS
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      http://getkirby.com
 * @copyright Bastian Allgeier
 * @license   http://getkirby.com/license
 */
class Content extends File {

  // cache for the raw content of the file
  protected $raw = null;

  // the data array with all fields/Variables
  protected $data = null;

  // cache for the detected language code
  protected $languageCode = null;

  /**
   * Constructor
   * 
   * @param object $file The parent File object
   */
  public function __construct(File $file) {

    $this->root      = $file->root();
    $this->id        = $file->id();
    $this->parent    = $file->parent();
    $this->type      = 'content';
    $this->filename  = $file->filename();
    $this->extension = $file->extension();

  }

  /**
   * Returns the plain text version of the text file
   * This also makes sure to remove BOM characters from 
   * text files to avoid parsing errors. 
   * 
   * @return string
   */
  public function raw() {

    if(!is_null($this->raw)) return $this->raw;

    $this->raw = f::read($this->root);
    $this->raw = str_replace("\xEF\xBB\xBF", '', $this->raw);    

    return $this->raw;

  }

  /**
   * Returns the name of the file without extension   
   * and without language code
   *
   * @return string
   */
  public function name() {
  
    if(!is_null($this->name)) return $this->name;

    // get the name without the extension
    $name = f::name($this->filename());
    
    // on multi-language projects, the filenames need some extra treatment.
    if(site::$multilang) {
      $name = preg_replace('!.(' . implode('|', site::instance()->languages()->codes()) . ')$!i', '', $name);
    }

    return $this->name = $name;
  
  }

  /**
   * Returns an array with all field names from the text file
   * 
   * @return array 
   */
  public function fields() {
  
    // if language support is switched off or this is the default language
    // file, simply return an array of array keys of the data array    
    if(!site::$multilang or $this->isDefaultContent()) return array_keys($this->data());

    // when language support is switched on, always look for 
    // the right fields in the default language content file
    return $this->page()->content(c::get('lang.default'))->fields();

  }

  /**
   * Returns an array with all keys and values/Variables
   * 
   * @param string $key Optional key to get a single item from the data array
   * @param mixed $default Optional default value if the item is not in the array
   * @return array
   */
  public function data($key = null, $default = null) {

    // getter for a specific data key
    if(!is_null($key)) {

      // if language support is switched off, or this is the default
      // language file, a fallback for missing/untranslated fields is not needed
      if(!site::$multilang or $this->isDefaultContent()) {
        return a::get($this->data(), $key, $default);
      }

      // get the full data array
      $data = $this->data();

      // if the field exists, just return its content 
      if(isset($data[$key])) return $data[$key];

      // load the default language content file for the parent page
      // and try to get the field from that file as a fallback
      return $this->page()->defaultContent()->$key();

    }

    // getter for the entire data array
    if(!is_null($this->data)) return $this->data;

    $raw = $this->raw();

    if(!$raw) return $this->data = array();

    $sections = preg_split('![\r\n]+[-]{4,}!i', $raw);
    $data     = array();
    
    foreach($sections AS $s) {

      $parts = explode(':', $s);  
      $key   = str::lower(preg_replace('![^a-z0-9]+!i', '_', trim($parts[0])));

      if(empty($key)) continue;
      
      $value = trim(implode(':', array_slice($parts, 1)));

      // store the key and value in the data array
      $this->data[$key] = new Variable($key, $value, $this);
    
    }

    return $this->data;

  }

  /**
   * Returns the language code of this file
   * If the file has no language code, 
   * the default language code will be returned
   * 
   * @return string
   */
  public function languageCode() {
    if(!site::$multilang) return false;
    if(!is_null($this->languageCode)) return $this->languageCode;    
    $code = str::match($this->filename(), '!\.([a-z]{2})\.' . $this->extension() . '$!i', 1);
    return (language::valid($code)) ? $code : c::get('lang.default');
  }

  /**
   * Checks if this is the content file for 
   * the default language. This is used to check for needed
   * fallbacks for missing stuff in translated files
   * 
   * @return boolean
   */
  public function isDefaultContent() {
    return c::get('lang.default') == $this->languageCode() ? true : false;
  }

  /**
   * Magic getter for Variables
   * i.e. $this->title()
   * 
   * @param string $key This is auto filled by PHP with the called method name
   * @param mixed $arguments Not used!
   * @return mixed
   */
  public function __call($key, $arguments = null) {    
    return $this->data($key);
  }

  /**
   * Setter for overwriting data
   * 
   * @param mixed $key
   * @param mixed $value
   */
  public function set($key, $value = '') {

    // make sure the data has been fetched at least once
    $this->data();

    if(is_array($key)) {
      foreach($key as $k => $v) $this->set($k, $v);
      return true;
    } else if(is_null($value)) {
      unset($this->data[$key]);
    } else {
      $this->data[$key] = new Variable($key, $value, $this);      
    }

  }

  /**
   * Magic setter
   * 
   * @param mixed $key
   * @param mixed $value
   */  
  public function __set($key, $value) {
    $this->set($key, $value);
  }

  /**
   * Legacy code to implement content->variables;
   */
  public function __get($key) {
    if($key == 'variables') {
      return $this->data();
    } else {
      return $this->$key();
    }
  }

  /**
   * Saves the data to the content txt file
   * Use this after setting/overwriting data to store it
   * 
   * @return boolean
   */
  public function save() {

    if(!\Kirby\Toolkit\Txtstore::write($this->root(), $this->toArray())) return false;

    // reset all data
    $this->raw  = null;
    $this->data = null;

    // fetch data again from scratch
    $this->raw();
    $this->data();

    return true;

  }

  /**
   * Creates a new content file for a site, page or file object
   * 
   * @param object $parent The parent site, page or file object
   * @param array $data An optional array of data, which should be written to the content file
   * @return object The final content object
   */
  static public function create($parent, $data = array()) {

    // start defininig the root for the content file
    $root = $parent->root();

    // pages get the uid added by default
    // since we don't know the desired template
    if(is_a($parent, 'Kirby\\CMS\\Page')) {
      $root .= DS . $parent->uid();
    } 

    // add the language if applicable
    if(site::$multilang) $root .= '.' . c::get('lang.current');

    // add the extension
    $root .= '.' . c::get('content.file.extension', 'txt');

    // try to create the content file
    if(!\Kirby\Toolkit\Txtstore::write($root, $data)) {
      raise('The content file could not be created', 'write-failed'); 
    }

    // reset the parent to make sure everything is up to date
    $parent->reset();

    if(is_a($parent, 'Kirby\\CMS\\Page')) {
      return $parent->content();
    } else {
      return $parent->meta();      
    }

  }

  /**
   * Converts the data array with all variable objects
   * into a clean array with strings for each value
   * 
   * @return array
   */
  public function toArray() {
    $data = array();
    foreach($this->data() as $key => $value) {
      $data[$key] = (string)$value;
    }
    return $data;
  }

  /**
   * Returns a more readable dump array for the dump() helper
   * 
   * @return array
   */
  public function __toDump() {

    $data = array();
    foreach($this->data() as $key => $value) $data[$key] = (string)$value;

    return array_merge(parent::__toDump(), array(
      'fields'       => $this->fields(),
      'data'         => $data,
      'languageCode' => $this->languageCode(),
    ));

  }

}