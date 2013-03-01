<?php 

/**
 * Plugin
 * 
 * Represents a single Kirby plugin
 * New plugins should all extend this class to gain
 * handy methods like clean initializing and loading of sub files. 
 * 
 * @package Kirby CMS
 */
class KirbyPlugin {

  // the full root of the plugin folder
  protected $root;

  // a KirbyObject with available info from a package.json file
  protected $info;

  // the name of the plugin
  protected $name;
  
  // the id of the plugin, which is also the folder name
  protected $id;
  
  // the plugin description, coming from the package.json file
  protected $description;
  
  // An attached object instance if needed
  protected $instance = null;

  /**
   * Installs a plugin and also initializes a child class of 
   * KirbyPlugin if available. 
   * 
   * @param string $id id/folder name of this plugin
   * @param string $root the full root to the plugin folder
   */
  static public function install($id, $root) {

    $file  = $root . DS . $id . '.php';
    $class = 'Kirby' . $id . 'Plugin';

    // try to load the main file
    if(file_exists($file)) include_once($file);
    
    // if a child class is available, use that. otherwise use the KirbyPlugin mother class 
    return (class_exists($class)) ? new $class($id, $root) : new self($id, $root);

  }

  /**
   * Constructor
   * 
   * Don't overwrite the constructor in child classes
   * Use the onInstall event method instead
   * 
   * @param string $id id/folder name of this plugin
   * @param string $root the full root to the plugin folder
   */
  public function __construct($id, $root) {
    $this->id   = $id;
    $this->root = $root;
    $this->onInstall();
  }

  // Events

  /**
   * Is being triggered within the constructor
   * and can be used in child classes to do something
   * as soon as the plugin is being loaded/installed
   * 
   * @return false
   */
  public function onInstall() {    
    return false;
  }

  /**
   * Is being triggered when the instance method
   * is called for the first time. Afterwards the
   * instance is cached and this won't be called again.
   *
   * Use this in child classes to create an instance of
   * any object you want to attach to the plugin
   * 
   * @param array $arguments Optional arguments to pass to the instance
   * @return false
   */
  public function onInit($arguments = array()) {
    return false;
  }

  /**
   * Returns the attached object instance. 
   * Makes sure to cache it and init it only once. 
   * 
   * @param array $arguments Optional arguments to pass to the instance
   * @return object
   */
  public function instance($arguments = array()) {
    if(!is_null($this->instance)) return $this->instance;
    return $this->instance = $this->onInit($arguments);
  }

  /**
   * Returns a KirbyObject object with all info from 
   * the package.json if available
   * 
   * @return object KirbyObject
   */
  public function info() {
    if(!is_null($this->info)) return $this->info;
    return $this->info = new KirbyObject(f::read($this->root . DS . 'package.json', 'json'));
  }

  /**
   * Loads an additional sub file of the plugin
   * Use it like this $this->load('lib' . DS . 'somefile.php')
   * to load somefile.php within the lib subfolder of the plugin folder
   * 
   * @param string $file The relative path to the loadable file. This can also be an array of files.
   */
  public function load($file) {

    if(is_array($file)) {
      foreach($file as $f) $this->load($f);
      return true;
    }

    require_once($this->root . DS . $file);
  
  }

  /**
   * Returns the id/folder name of the plugin
   * 
   * @return string
   */
  public function id() {
    return $this->id;
  }

  /**
   * Returns the name name of the plugin
   * 
   * @return string
   */
  public function name() {
    if(!is_null($this->name)) return $this->name;    
    $name = $this->info()->name();
    return $this->name = ($name != '') ? $name : $this->id();   
  }

  /**
   * Returns the version number of the plugin
   * 
   * @return float
   */
  public function version() {
    return $this->info()->version();
  }

  /**
   * Renders some helpful information about
   * the plugin when you try to echo it. 
   * 
   * @return string
   */
  public function __toString() {

    $html = array();

    $html[] = 'Plugin: ' . $this->name();
    $html[] = 'Description: ' . $this->info()->description();
    $html[] = 'Version: ' . $this->version();
    $html[] = 'Author: ' . $this->info()->author();
    $html[] = 'URL: ' . $this->info()->url();

    return '<pre>' . implode('<br />', $html) . '</pre>';

  }

}