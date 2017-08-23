<?php

namespace Drupal\commerce_usps;

/**
 * Class XMLParser.
 *
 * @package Drupal\fyp
 */
class FYPXMLParser {

  /**
   * @param $string
   *
   * @return mixed
   *
   */
  public function parse($string) {
    $data = [];
    $encoding = $this->encode($string);
    $string = $this->deleteElements($string, "?");
    $string = $this->deleteElements($string, "!");
    $data = $this->read($string, $data, $encoding);
    unset($string);
    return ($data);
  }

  /**
   * @param $string
   *
   * @return bool|string
   */
  public function encode($string) {
    $encoding = "";
    if (substr_count($string, "<?xml")) {
      $start = strpos($string, "<?xml") + 5;
      $end = strpos($string, ">", $start);
      $content = substr($string, $start, $end - $start);
      $encoding_start = strpos($content, "encoding=\"") + 10;
      $encoding_end = strpos($content, "\"", $encoding_start);
      $encoding = substr($content, $encoding_start, $encoding_end - $encoding_start);
    }

    return $encoding;
  }

  /**
   * @param $string
   * @param $char
   *
   * @return string
   */
  public function deleteElements($string, $char) {
    while (substr_count($string, "<$char")) {
      $start = strpos($string, "<$char");
      $end = strpos($string, ">", $start + 1) + 1;
      $string = substr($string, 0, $start) . substr($string, $end);
    }
    return $string;
  }

  /**
   * @param $string
   * @param $data
   * @param string $encoding
   *
   * @return mixed
   *
   */
  public function read($string, $data, $encoding = '') {
    while ($node = $this->nextNode($string)) {
      $temp_data = "";
      $start = strpos($string, ">", strpos($string, "<$node")) + 1;
      $end = strpos($string, "</$node>", $start);
      $this_content = trim(substr($string, $start, $end - $start));
      $string = trim(substr($string, $end + strlen($node) + 3));
      if (substr_count($this_content, "<")) {
        $temp_data = $this->read($this_content, $temp_data, $encoding);
        $data[$node][] = $temp_data;
      }
      else {
        if ($encoding == "UTF-8") {
          $this_content = utf8_decode($this_content);
        }
        $this_content = str_replace("&gt;", ">", $this_content);
        $this_content = str_replace("&lt;", "<", $this_content);
        $this_content = str_replace("&quote;", "\"", $this_content);
        $this_content = str_replace("&#39;", "'", $this_content);
        $this_content = str_replace("&amp;", "&", $this_content);
        $data[$node][] = $this_content;
      }
    }
    unset($string);
    return $data;
  }

  /**
   * @param $string
   *
   * @return bool|mixed|string
   *
   */
  public function nextNode($string) {
    if (substr_count($string, "<") != substr_count($string, "/>")) {
      $start = strpos($string, "<") + 1;
      while (substr($string, $start, 1) == "/") {
        if (substr_count($string, "<")) {
          unset($string);
          return "";
        }
        $start = strpos($string, "<", $start) + 1;
      }
      $end = strpos($string, ">", $start);
      $node = substr($string, $start, $end - $start);
      if ($node[strlen($node) - 1] == "/") {
        $string = substr($string, $end + 1);
        $node = $this->nextNode($string);
      }
      else {
        if (substr_count($node, " ")) {
          $node = substr($node, 0, strpos($string, " ", $start) - $start);
        }
      }
    }
    unset($string);
    return isset($node) ? $node : "";
  }
}
