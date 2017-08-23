<?php

namespace Drupal\commerce_usps\Controller;

use Drupal\fyp\FYPXMLParser;

class USPS {

  var $server = "";

  var $user = "";

  var $pass = "";

  var $service = "";

  var $dest_zip;

  var $orig_zip;

  var $length;

  var $width;

  var $height;

  var $fc_type = "";

  var $pounds;

  var $ounces;

  var $container = "None";

  var $size = "REGULAR";

  var $machinable;

  var $country;

  var $mailtype;

  function setServer($server) {
    $this->server = $server;
  }

  function setUserName($user) {
    $this->user = $user;
  }

  function setPass($pass) {
    $this->pass = $pass;
  }

  function setService($service) {
    /* Must be: Express, Priority, or Parcel */
    $this->service = $service;
  }

  function setMailType($mailtype) {
    $this->mailtype = $mailtype;
  }

  function setCountry($country) {
    $this->country = $country;
  }

  function setDestZip($sending_zip) {
    /* Must be 5 digit zip (No extension) */
    $this->dest_zip = $sending_zip;
  }

  function setOrigZip($orig_zip) {
    $this->orig_zip = $orig_zip;
  }

  function setLength($length) {
    $this->length = $length;
  }

  function setWidth($width) {
    $this->width = $width;
  }

  function setHeight($height) {
    $this->height = $height;
  }

  function setWeight($pounds, $ounces = 0) {
    /* Must weight less than 70 lbs. */
    $this->pounds = $pounds;
    $this->ounces = $ounces;
  }

  function setContainer($cont) {
    /*
    Valid Containers
            Package Name             Description
    Express Mail
            None                For someone using their own package
            0-1093 Express Mail         Box, 12.25 x 15.5 x
            0-1094 Express Mail         Tube, 36 x 6
            EP13A Express Mail         Cardboard Envelope, 12.5 x 9.5
            EP13C Express Mail         Tyvek Envelope, 12.5 x 15.5
            EP13F Express Mail         Flat Rate Envelope, 12.5 x 9.5

    Priority Mail
            None                For someone using their own package
            0-1095 Priority Mail        Box, 12.25 x 15.5 x 3
            0-1096 Priority Mail         Video, 8.25 x 5.25 x 1.5
            0-1097 Priority Mail         Box, 11.25 x 14 x 2.25
            0-1098 Priority Mail         Tube, 6 x 38
            EP14 Priority Mail         Tyvek Envelope, 12.5 x 15.5
            EP14F Priority Mail         Flat Rate Envelope, 12.5 x 9.5

    Parcel Post
            None                For someone using their own package
    */

    $this->container = $cont;
  }

  function setSize($size) {
    /* Valid Sizes
    Package Size                Description        Service(s) Available
    Regular package length plus girth     (84 inches or less)    Parcel Post
                                    Priority Mail
                                    Express Mail

    Large package length plus girth        (more than 84 inches but    Parcel Post
                        not more than 108 inches)    Priority Mail
                                    Express Mail

    Oversize package length plus girth   (more than 108 but        Parcel Post
                         not more than 130 inches)

    */
    $this->size = $size;
  }

  function setMachinable($mach) {
    /* Required for Parcel Post only, set to True or False */
    $this->machinable = $mach;
  }

  function getDomesticPrice() {
    global $settings;
    // may need to urlencode xml portion
    $str = $this->server . "?API=RateV4&XML=<RateV4Request%20USERID=\"";
    $str .= htmlspecialchars($this->user, ENT_XML1) . "\"%20PASSWORD=\"" . htmlspecialchars($this->pass, ENT_XML1) . "\"><Package%20ID=\"0\">";
    $str .= "<Service>" . htmlspecialchars($this->service, ENT_XML1) . "</Service>";
    if (trim($this->fc_type) != "") {
      $str .= "<FirstClassMailType>" . htmlspecialchars($this->fc_type, ENT_XML1) . "</FirstClassMailType>";
    }
    $str .= "<ZipOrigination>" . htmlspecialchars($this->orig_zip, ENT_XML1) . "</ZipOrigination>";
    $str .= "<ZipDestination>" . htmlspecialchars($this->dest_zip, ENT_XML1) . "</ZipDestination>";
    $str .= "<Pounds>" . htmlspecialchars($this->pounds, ENT_XML1) . "</Pounds><Ounces>" . htmlspecialchars($this->ounces, ENT_XML1) . "</Ounces>";
    $str .= "<Container>" . htmlspecialchars($this->container, ENT_XML1) . "</Container>" .
      "<Size>" . htmlspecialchars($this->size, ENT_XML1) . "</Size>" .
      '<Width>' . htmlspecialchars($this->width, ENT_XML1) . '</Width>' .
      '<Length>' . htmlspecialchars($this->length, ENT_XML1) . '</Length>' .
      '<Height>' . htmlspecialchars($this->height, ENT_XML1) . '</Height>';
    $str .= "<Machinable>" . htmlspecialchars($this->machinable, ENT_XML1) . "</Machinable>";
    $str .= "<ShipDate>" . htmlspecialchars(strftime('%Y-%m-%d', time()), ENT_XML1) . "</ShipDate>";
    $str .= "</Package></RateV4Request>";
    //echo "<hr><p><b>XML data sent to USPS (service: ".$this->service."):</b><br>".htmlspecialchars($str)."</p>";
    $body = "";

    $cu = curl_init($str);

    curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($cu);
    //echo "<p><b>USPS answer:</b><br>".htmlspecialchars($body)."</p>";
    //exit;
    //die();
    if (trim($body) != "") {
      if (strpos($body, "Error") === FALSE) {
        $xmlParser = new FYPXMLParser();
        $data = $xmlParser->parse($body);
        //echo "<pre>";
        //print_r($data);
        if (isset($data['RateV4Response'][0]['Package'][0]['Postage'][0])) {
          $tmp_price = $data['RateV4Response'][0]['Package'][0]['Postage'][0];
          return ($tmp_price['Rate'][0]);
        }
        else {
          return FALSE;
        }
      }
      else {
        return FALSE;
      }
    }
    else {
      return (FALSE);
    }
  }

  function GetInternationalPrice() {
    $parts = explode("=", $this->mailtype);
    $mailtype = strtolower(trim($parts[0]));
    $service_id = strtolower(trim($parts[1]));

    $str = $this->server . "?API=IntlRate&XML=<IntlRateRequest%20USERID=\"";
    $str .= htmlspecialchars($this->user, ENT_XML1) . "\"%20PASSWORD=\"" . htmlspecialchars($this->pass, ENT_XML1) . "\"><Package%20ID=\"0\">";
    $str .= "<Pounds>" . htmlspecialchars($this->pounds, ENT_XML1) . "</Pounds><Ounces>" . htmlspecialchars($this->ounces, ENT_XML1) . "</Ounces>";
    $str .= "<MailType>" . htmlspecialchars($mailtype, ENT_XML1) . "</MailType><Country>" . htmlspecialchars($this->country, ENT_XML1) . "</Country>";
    $str .= "</Package></IntlRateRequest>";

    //echo "<hr><p><b>XML data sent to USPS (service: ".$this->mailtype."):</b><br>".htmlspecialchars($str)."</p>";
    $body = "";
    $f = @fopen($str, "r");
    $cu = curl_init($str);

    curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($cu);

    //echo "<p><b>USPS answer:</b><br>".htmlspecialchars($body)."</p>";
    //exit;
    //header('Content-Type: application/xml');
    //print ($body);
    //exit;
    if (trim($body) != "") {
      if (strpos($body, "Error") === FALSE) {
        //get price
        $service_name = "";
        switch ($mailtype) {
          case "envelope" : {
            switch ($service_id) {
              case "1" :
                $service_name = "Express Mail International (EMS)";
                break; // Global Express Guaranteed Non-Document Service
              case "2" :
                $service_name = "Priority Mail International";
                break; //
              //case "3" : $service_name = "First-Class Mail International"; break; // Global Priority Mail - Flat-rate Envelope (Large)
              case "4" :
                $service_name = "Global Express Guaranteed";
                break; // Global Priority Mail - Flat-rate Envelope (Small)
              case "5" :
                $service_name = "Global Express Guaranteed Document";
                break; //Global Priority Mail - Variable Weight (Single)
              //case "6" : $service_name = "Airmail Letter Post"; break; //
              //case "7" : $service_name = "Economy (Surface) Letter Post"; break; //
              case "8" :
                $service_name = "Priority Mail International Flat-Rate Envelope";
                break;  // Priority Mail International Flat Rate Envelope
              case "10" :
                $service_name = "Express Mail International (EMS) Flat-Rate Envelope";
                break;     //Express Mail International (EMS) Flat Rate Envelope
            }
            break;
          }

          case "package" : {
            switch ($service_id) {
              case "1" :
                $service_name = "Priority Mail Express International&lt;sup&gt;&#8482;&lt;/sup&gt;";
                break;  // Priority Mail Express International Non-Document Service
              case "2" :
                $service_name = "Priority Mail International&lt;sup&gt;&#174;&lt;/sup&gt;";
                break; //Priority Mail International
              //case "3" : $service_name = "First-Class Mail International"; break; //Global Priority Mail - Flat-rate Envelope (Large)
              case "4" :
                $service_name = "Global Express Guaranteed";
                break; //Global Priority Mail - Flat-rate Envelope (Small)
              case "5" :
                $service_name = "Global Express Guaranteed Document";
                break;  // Global Priority Mail - Variable Weight (Single)
              case "6" :
                $service_name = "Global Express Guaranteed Non-Document Rectangular";
                break;    // Airmail Letter Post
              case "7" :
                $service_name = "Global Express Guaranteed Non-Document Non-Rectangular";
                break;    //Airmail Parcel Post
              case "8" :
                $service_name = "Priority Mail International Flat-Rate Envelope";
                break;  //Economy (Surface) Letter Post
              case "9" :
                $service_name = "Priority Mail International Flat-Rate Box";
                break;  //Economy (Surface) Parcel Post
              case "10" :
                $service_name = "Express Mail International (EMS) Flat-Rate Envelope";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "11" :
                $service_name = "Priority Mail International Large Flat-Rate Box";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "12" :
                $service_name = "USPS GXG Envelopes";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "13" :
                $service_name = "First Class Mail International Letters";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "14" :
                $service_name = "First Class Mail International Large Envelope";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "15" :
                $service_name = "First Class Mail International Package";
                break; //Express Mail International (EMS) Flat Rate Envelope
            }
            break;
          }
          case "postcards or aerogrammes" : {
            switch ($service_id) {
              case "0" :
                $service_name = "Postcards - Airmail";
                break;
              case "1" :
                $service_name = "Aerogrammes - Airmail";
                break;
            }
            break;
          }
        }

        $xmlParser = new FYPXMLParser();
        $data = $xmlParser->parse($body);
        //echo "<pre>";
        //print_r($data);
        //echo "</pre>";
        //exit;

        if (isset($data['IntlRateResponse'][0]['Package'][0]['Service'])) {
          $services = $data['IntlRateResponse'][0]['Package'][0]['Service'];
          for ($i = 0; $i < count($services); $i++) {
            $service = $services[$i];
            if (trim(strtolower($service['SvcDescription'][0])) == strtolower($service_name)) {
              return $service['Postage'][0];
            }
          }
        }
        return FALSE;
      }
      else {
        return FALSE;
      }
    }
    else {
      return (FALSE);
    }
  }

  function getDomesticRate() {
    global $settings;
    // may need to urlencode xml portion
    $str = $this->server . "?API=RateV4&XML=<RateV4Request%20USERID=\"";
    $str .= htmlspecialchars($this->user, ENT_XML1) . "\"%20PASSWORD=\"" . htmlspecialchars($this->pass, ENT_XML1) . "\"><Package%20ID=\"0\">";
    $str .= "<Service>" . htmlspecialchars($this->service, ENT_XML1) . "</Service>";
    if (trim($this->fc_type) != "") {
      $str .= "<FirstClassMailType>" . htmlspecialchars($this->fc_type, ENT_XML1) . "</FirstClassMailType>";
    }
    $str .= "<ZipOrigination>" . htmlspecialchars($this->orig_zip, ENT_XML1) . "</ZipOrigination>";
    $str .= "<ZipDestination>" . htmlspecialchars($this->dest_zip, ENT_XML1) . "</ZipDestination>";
    $str .= "<Pounds>" . htmlspecialchars($this->pounds, ENT_XML1) . "</Pounds><Ounces>" . htmlspecialchars($this->ounces, ENT_XML1) . "</Ounces>";
    $str .= "<Container>" . htmlspecialchars($this->container, ENT_XML1) . "</Container>" .
      "<Size>" . htmlspecialchars($this->size, ENT_XML1) . "</Size>" .
      '<Width>' . htmlspecialchars($this->width, ENT_XML1) . '</Width>' .
      '<Length>' . htmlspecialchars($this->length, ENT_XML1) . '</Length>' .
      '<Height>' . htmlspecialchars($this->height, ENT_XML1) . '</Height>';
    $str .= "<Machinable>" . htmlspecialchars($this->machinable, ENT_XML1) . "</Machinable>";
    $str .= "<ShipDate>" . htmlspecialchars(strftime('%Y-%m-%d', time()), ENT_XML1) . "</ShipDate>";
    $str .= "</Package></RateV4Request>";
    //echo "<hr><p><b>XML data sent to USPS (service: ".$this->service."):</b><br>".htmlspecialchars($str)."</p>";
    $body = "";

    $cu = curl_init($str);

    curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($cu);
    //echo "<p><b>USPS answer:</b><br>".htmlspecialchars($body)."</p>";
    //exit;
    //die();
    if (trim($body) != "") {
      if (strpos($body, "Error") === FALSE) {
        $xmlParser = new FYPXMLParser();
        $data = $xmlParser->parse($body);
        //echo "<pre>";
        //print_r($data);
        if (isset($data['RateV4Response'][0]['Package'][0]['Postage'][0])) {
          return ($data);
        }
        else {
          return FALSE;
        }
      }
      else {
        return FALSE;
      }
    }
    else {
      return (FALSE);
    }
  }

  function GetInternationalRate() {
    $parts = explode("=", $this->mailtype);
    $mailtype = strtolower(trim($parts[0]));
    $service_id = strtolower(trim($parts[1]));

    $str = $this->server . "?API=IntlRate&XML=<IntlRateRequest%20USERID=\"";
    $str .= htmlspecialchars($this->user, ENT_XML1) . "\"%20PASSWORD=\"" . htmlspecialchars($this->pass, ENT_XML1) . "\"><Package%20ID=\"0\">";
    $str .= "<Pounds>" . htmlspecialchars($this->pounds, ENT_XML1) . "</Pounds><Ounces>" . htmlspecialchars($this->ounces, ENT_XML1) . "</Ounces>";
    $str .= "<MailType>" . htmlspecialchars($mailtype, ENT_XML1) . "</MailType><Country>" . htmlspecialchars($this->country, ENT_XML1) . "</Country>";
    $str .= "</Package></IntlRateRequest>";

    //echo "<hr><p><b>XML data sent to USPS (service: ".$this->mailtype."):</b><br>".htmlspecialchars($str)."</p>";
    $body = "";
    $f = @fopen($str, "r");
    $cu = curl_init($str);

    curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($cu);

    //echo "<p><b>USPS answer:</b><br>".htmlspecialchars($body)."</p>";
    //exit;
    //header('Content-Type: application/xml');
    //print ($body);
    //exit;
    if (trim($body) != "") {
      if (strpos($body, "Error") === FALSE) {
        //get price
        $service_name = "";
        switch ($mailtype) {
          case "envelope" : {
            switch ($service_id) {
              case "1" :
                $service_name = "Express Mail International (EMS)";
                break; // Global Express Guaranteed Non-Document Service
              case "2" :
                $service_name = "Priority Mail International";
                break; //
              //case "3" : $service_name = "First-Class Mail International"; break; // Global Priority Mail - Flat-rate Envelope (Large)
              case "4" :
                $service_name = "Global Express Guaranteed";
                break; // Global Priority Mail - Flat-rate Envelope (Small)
              case "5" :
                $service_name = "Global Express Guaranteed Document";
                break; //Global Priority Mail - Variable Weight (Single)
              //case "6" : $service_name = "Airmail Letter Post"; break; //
              //case "7" : $service_name = "Economy (Surface) Letter Post"; break; //
              case "8" :
                $service_name = "Priority Mail International Flat-Rate Envelope";
                break;  // Priority Mail International Flat Rate Envelope
              case "10" :
                $service_name = "Express Mail International (EMS) Flat-Rate Envelope";
                break;     //Express Mail International (EMS) Flat Rate Envelope
            }
            break;
          }

          case "package" : {
            switch ($service_id) {
              case "1" :
                $service_name = "Priority Mail Express International&lt;sup&gt;&#8482;&lt;/sup&gt;";
                break;  // Priority Mail Express International Non-Document Service
              case "2" :
                $service_name = "Priority Mail International&lt;sup&gt;&#174;&lt;/sup&gt;";
                break; //Priority Mail International
              //case "3" : $service_name = "First-Class Mail International"; break; //Global Priority Mail - Flat-rate Envelope (Large)
              case "4" :
                $service_name = "Global Express Guaranteed";
                break; //Global Priority Mail - Flat-rate Envelope (Small)
              case "5" :
                $service_name = "Global Express Guaranteed Document";
                break;  // Global Priority Mail - Variable Weight (Single)
              case "6" :
                $service_name = "Global Express Guaranteed Non-Document Rectangular";
                break;    // Airmail Letter Post
              case "7" :
                $service_name = "Global Express Guaranteed Non-Document Non-Rectangular";
                break;    //Airmail Parcel Post
              case "8" :
                $service_name = "Priority Mail International Flat-Rate Envelope";
                break;  //Economy (Surface) Letter Post
              case "9" :
                $service_name = "Priority Mail International Flat-Rate Box";
                break;  //Economy (Surface) Parcel Post
              case "10" :
                $service_name = "Express Mail International (EMS) Flat-Rate Envelope";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "11" :
                $service_name = "Priority Mail International Large Flat-Rate Box";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "12" :
                $service_name = "USPS GXG Envelopes";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "13" :
                $service_name = "First Class Mail International Letters";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "14" :
                $service_name = "First Class Mail International Large Envelope";
                break; //Express Mail International (EMS) Flat Rate Envelope
              case "15" :
                $service_name = "First Class Mail International Package";
                break; //Express Mail International (EMS) Flat Rate Envelope
            }
            break;
          }
          case "postcards or aerogrammes" : {
            switch ($service_id) {
              case "0" :
                $service_name = "Postcards - Airmail";
                break;
              case "1" :
                $service_name = "Aerogrammes - Airmail";
                break;
            }
            break;
          }
        }

        $xmlParser = new FYPXMLParser();
        $data = $xmlParser->parse($body);
        //echo "<pre>";
        //print_r($data);
        //echo "</pre>";
        //exit;

        if (isset($data['IntlRateResponse'][0]['Package'][0]['Service'])) {
          $services = $data['IntlRateResponse'][0]['Package'][0]['Service'];
          for ($i = 0; $i < count($services); $i++) {
            $service = $services[$i];
            if (trim(strtolower($service['SvcDescription'][0])) == strtolower($service_name)) {
              return $service;
            }
          }
        }
        return FALSE;
      }
      else {
        return FALSE;
      }
    }
    else {
      return (FALSE);
    }
  }

}