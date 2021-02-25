<?php

class Content {
    protected $content;
    protected $page_id;
  
    public function __construct($page_id, $content) {
      $this->page_id = $page_id;
      $this->content = $content;
    }
  
    public function get_content() {
      return $this->content;
    }
  
    public function set_content($new_content) {
      $this->content = $new_content;
    }
  
    public function get_page_id() {
      return $this->page_id;
    }
  
    public function set_page_id($new_id) {
      $this->page_id = $new_id;
    }
  }
  
  class Page {
    protected $content;
    protected $id;
    protected $keywords;
    protected $path;
    protected $title;
    protected $headers;
    protected $paragraphs;
    protected $desc;
  
    // $keywords is an array of objects.
    // $path is a string
    public function __construct($path) {
      $this->content = null;
      $this->headers = null;
      $this->id = null;
      $this->keywords = null;
      //$this->paragraphs = null;
      $this->path = $path;
      $this->title = null;
      $this->desc = null;
    }
  
    public function get_path() {
      return $this->path;
    }
    public function set_path($new_path) {
      $this->path = $new_path;
    }
  
    public function get_content() {
      return $this->content;
    }
    public function set_content($new_content) {
      $this->content = $new_content;
    }
  
    public function get_headers() {
      return $this->headers;
    }
    public function set_headers($new_headers) {
      $this->headers = $new_headers;
    }
  
    /*public function get_paragraphs() {
      return $this->paragraphs;
    }
    public function set_paragraphs($new_paragraphs) {
      $this->paragraphs = $new_paragraphs;
    }*/
  
    public function get_keywords() {
      return $this->keywords;
    }
    public function set_keywords($new_keywords) {
      $this->keywords = $new_keywords;
    }
  
    public function get_id() {
      return $this->id;
    }
    public function set_id($new_id) {
      $this->id = $new_id;
    }
  
    public function get_title() {
      return $this->title;
    }
    public function set_title($new_title) {
      $this->title = $new_title;
    }
  
    public function get_desc() {
      return $this->desc;
    }
    public function set_desc($new_desc) {
      $this->desc = $new_desc;
    }
  }
  
  class Token {
    protected $dupe_total = 1;
    protected $text = '';
  
    // $keyword is a string.
    // $dupe_total is an integer.
    public function __construct($text, $dupe_total) {
      $this->text = $text;
      $this->dupe_total = $dupe_total;
    }
  
    public function get_text () {
      return $this->text;
    }
  
    public function get_dupe_total () {
      return $this->dupe_total;
    }
  }
  
  class Header {
    public $element;
    public $text;
    public $tag;
    //public $pos; // Location of this header within the main content.
    public $paragraph; // Paragraph associated with this header.
    public $id;
  
    public function __construct($element, $tag) {
      $this->element = $element;
      $this->text = trim($element->textContent);
      $this->tag = $tag;
      //$this->pos = null;
      $this->paragraph = null;
      $this->id = null;
    }
  
    public function get_element() {
      return $this->element;
    }
  
    public function get_text() {
      return $this->text;
    }
    public function set_text($new_text) {
      $this->text = $new_text;
    }
  
    public function get_tag() {
      return $this->tag;
    }
    public function set_tag($new_tag) {
      $this->tag = $new_tag;
    }
  
    public function get_id() {
      return $this->id;
    }
    public function set_id($new_id) {
      $this->id = $new_id;
    }
  
    public function get_paragraph() {
      return $this->paragraph;
    }
    public function set_paragraph($new_paragraph) {
      $this->paragraph = $new_paragraph;
    }
  }
?>