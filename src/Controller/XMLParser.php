<?php

namespace Drupal\commerce_usps\Controller;

/**
 * Class XMLParser.
 *
 * @package Drupal\commerce_usps
 */
class XMLParser {

  # Contructor
  function XMLParser() {
    return $this;
  }

  # Parse XML
  function parse($String) {
    $Data = [];
    $Encoding = $this->xml_encoding($String);
    $String = $this->xml_deleteelements($String, "?");
    $String = $this->xml_deleteelements($String, "!");
    $Data = $this->xml_readxml($String, $Data, $Encoding);
    unset($String);
    return ($Data);
  }

  # Get encoding of xml
  function xml_encoding($String) {
    if (substr_count($String, "<?xml")) {
      $Start = strpos($String, "<?xml") + 5;
      $End = strpos($String, ">", $Start);
      $Content = substr($String, $Start, $End - $Start);
      $EncodingStart = strpos($Content, "encoding=\"") + 10;
      $EncodingEnd = strpos($Content, "\"", $EncodingStart);
      $Encoding = substr($Content, $EncodingStart, $EncodingEnd - $EncodingStart);
    }
    else {
      $Encoding = "";
    }
    return $Encoding;
  }

  # Delete elements
  function xml_deleteelements($String, $Char) {
    while (substr_count($String, "<$Char")) {
      $Start = strpos($String, "<$Char");
      $End = strpos($String, ">", $Start + 1) + 1;
      $String = substr($String, 0, $Start) . substr($String, $End);
    }
    return $String;
  }

  # Read XML and transform into array
  function xml_readxml($String, $Data, $Encoding = '') {
    while ($Node = $this->xml_nextnode($String)) {
      $TmpData = "";
      $Start = strpos($String, ">", strpos($String, "<$Node")) + 1;
      $End = strpos($String, "</$Node>", $Start);
      $ThisContent = trim(substr($String, $Start, $End - $Start));
      $String = trim(substr($String, $End + strlen($Node) + 3));
      if (substr_count($ThisContent, "<")) {
        $TmpData = $this->xml_readxml($ThisContent, $TmpData, $Encoding);
        $Data[$Node][] = $TmpData;
      }
      else {
        if ($Encoding == "UTF-8") {
          $ThisContent = utf8_decode($ThisContent);
        }
        $ThisContent = str_replace("&gt;", ">", $ThisContent);
        $ThisContent = str_replace("&lt;", "<", $ThisContent);
        $ThisContent = str_replace("&quote;", "\"", $ThisContent);
        $ThisContent = str_replace("&#39;", "'", $ThisContent);
        $ThisContent = str_replace("&amp;", "&", $ThisContent);
        $Data[$Node][] = $ThisContent;
      }
    }
    unset($String);
    return $Data;
  }

  # Get next node
  function xml_nextnode($String) {
    if (substr_count($String, "<") != substr_count($String, "/>")) {
      $Start = strpos($String, "<") + 1;
      while (substr($String, $Start, 1) == "/") {
        if (substr_count($String, "<")) {
          unset($String);
          return "";
        }
        $Start = strpos($String, "<", $Start) + 1;
      }
      $End = strpos($String, ">", $Start);
      $Node = substr($String, $Start, $End - $Start);
      if ($Node[strlen($Node) - 1] == "/") {
        $String = substr($String, $End + 1);
        $Node = $this->xml_nextnode($String);
      }
      else {
        if (substr_count($Node, " ")) {
          $Node = substr($Node, 0, strpos($String, " ", $Start) - $Start);
        }
      }
    }
    unset($String);
    return isset($Node) ? $Node : "";
  }
}
