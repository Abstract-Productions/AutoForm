<?php
  # AutoForm by Mark Hall
  # https://abstract-productions.net
  if (!isset($_SESSION)) session_start();
  if (empty($_SESSION['start_timestamp'])) $_SESSION['start_timestamp'] = time();

  class AutoForm {
    public $header;
    public $footer;
    public $Input;
    public $Sanitized;
 
    public $form_name;
    public $method;
    public $enctype;
    public $ajax;
    public $ajax_as_you_go;
    public $submit_label;
    public $errors = [];
    public $fields = [];
    public $robot_delay;
    public $pages;

    public $form_valid = true;

    public function __construct($Options = []) {
      $Options['method'] = strtoupper($Options['method'] ?? "BOTH");
      $this->method = $Options['method'] == "BOTH" ? "POST" : $Options['method'];
      if ($Options['method'] == "BOTH") $this->Input = array_merge($_GET, $_POST);
      else $this->Input = $this->method == "POST" ? $_POST : $_GET;
      $this->Sanitized = $this->sanitize($this->Input);
      $this->form_name = $Options['form_name'] ?? "form1";
      $this->enctype = $Options['enctype'] ?? "";
      $this->ajax = $Options['ajax'] ?? true;
      $this->ajax_as_you_go = $Options['ajax_as_you_go'] ?? false;
      $this->submit_label = $Options['submit_label'] ?? "Submit";
      $this->header = $Options['header'] ?? "";
      $this->footer = $Options['footer'] ?? "";
      $this->robot_delay = $Options['robot_delay'] ?? 5;
      $this->pages = 1;
      if (empty($this->Input['af_page'])) $this->Input['af_page'] = 1;
    }

    public function sanitize($Input) {
      if (!is_array($Input)) return htmlspecialchars($Input);
      foreach ($Input as $key => $value) $Input[$key] = $this->sanitize($value);
      return $Input;
    }

    public function execute($Fields = []) {
      if (!empty($Fields)) $this->add_fields($Fields);
      if (!$this->form_valid) die("ERROR: Invalid form settings");

      if (in_array($this->Input['af_action'] ?? "", [hash("sha256", "continue$_SESSION[start_timestamp]"), hash("sha256", "ajax$_SESSION[start_timestamp]")])) {
        $this->errors = $this->validate($this->Input['af_up_to'] ?? "");
        if ($this->Input['af_action'] == hash("sha256", "ajax$_SESSION[start_timestamp]")) {
          print empty($this->errors) ? "OK" : json_encode($this->errors);
          return false;
        }
        if (empty($this->errors)) {
          if ($this->Input['af_page'] == $this->pages) return true;
          else {
            $this->inp_set("af_page", $this->Input['af_page'] + 1);
            $this->inp_set("af_action", "");
          }
        }
      }
      $this->display_form();
      return false;
    }

    public function validate($up_to = "") {
      $validate_response = [];
      $Valid = [];
      foreach ($this->fields as $Field) {
        if ($Field['type'] == "html") continue;
        if ($Field['page'] > $this->Input['af_page']) continue;
        $res = "";
        
        if (is_callable($Field['validate'])) {
          $res = $Field['validate']($this->Input[$Field['field_name']] ?? "", $this);
        }
        else if ($Field['validate']) {
          $check = $this->Input[$Field['field_name'] ?? ""] ?? "";
          if (is_array($Field['validate'])) {
            foreach ($Field['validate'] as $v_type => $Values) {
              $pregs = [
                "default" => '/x\by/',
                "required" => '/.+/',
                "email" => '/^(([^\r\n\[\]\(\)"@,:;\. ]+)\.?)*[^\r\n\[\]\(\)"@,:;\. ]+'.
                           '@(\[?([0-9]{1,3}\.){3}[0-9]{1,3}\]?|'.
                           '(([a-z0-9][a-z0-9\-]*)\.)+[a-z]+)$/i',
                "integer" => '/^-?[0-9]+$/',
                "number" => '/^-?[0-9]+(\.[0-9]+)?$/',
                "date" => '/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/',
                "postal" => '/^[abceghj-nprstvxy][0-9][a-z] ?[0-9][a-z][0-9]$/i',
                "zip" => '^[0-9]{5}([- ][0-9]{4})?$/',
                "phone" => '/^(\+?1|\+?1[ \-\.])?\(?[2-9][0-9]{2}\)?[ \-\.]?[2-9][0-9]{2}[ \-\.]?[0-9]{4}$/'
              ];
              if (in_array($v_type, array_keys($pregs))) {
                if (!preg_match($pregs[$v_type], $check)) {
                  $res = $Values;
                  break;
                }
              }
              else if ($v_type == "province") {
                if (!in_array($check, array_keys($this->Province_List))) $res = $Values;
              }
              else if ($v_type == "state") {
                if (!in_array($check, array_keys($this->State_List))) $res = $Values;
              }
              else if ($v_type == "country") {
                if (!in_array($check, array_keys($this->Country_List))) $res = $Values;
              }
              else if (preg_match('/^(>=?|<=?|<>|==?|!=) ?[0-9]+(\.[0-9]+)?(,(>=?|<=?|<>|==?|!=) ?[0-9]+(\.[0-9]+)?)*$/', $v_type)) {
                $Expr = explode(",", $v_type);
                foreach ($Expr as $expr) {
                  preg_match('/^(>=?|<=?|<>|==?|!=) ?([0-9]+(\.[0-9]+)?)/', $expr, $match);
                  $expr_res = match($match[1]) {
                    ">" => $check > $match[2],
                    ">=" => $check >= $match[2],
                    "<" => $check < $match[2],
                    "<=" => $check <= $match[2],
                    "=", "==" => $check == $match[2],
                    "<>", "!=" => $check != $match[2],
                    default => false
                  };
                  if ($expr_res) {
                    $res = $Values;
                    break 2;
                  }
                }
              }
              else if (is_array($Values)) {
                foreach ($Values as $val => $val_res) {
                  if (($v_type == "regex" && preg_match($val, $check)) ||
                      ($v_type == "match" && $check == $val)) {
                    $res = $val_res;
                    break 2;
                  }
                }
              }
            }
          }
          else if ($check === "") {
            if (is_string($Field['validate'])) $res = $Field['validate'];
            else $res = 'Field "'.($Field['label'] ?: $Field['field_name']).'" is missing';
          }
        }
        if ($res) $validate_response[$Field['field_name']] = is_callable($res) ? $res($this) : $res;
        else $Valid[] = $Field['field_name'];
        if ($Field['field_name'] == $up_to) break;
      }
      
      if ($up_to === "" && $this->Input['af_page'] == $this->pages) {
        $delay = time() - $_SESSION['start_timestamp'];
        if ($delay < $this->robot_delay || $delay > 3600) {
          $validate_response['other-errs'] = "Possible robot detected";
        }
      }
      if ($up_to !== "" || !empty($validate_response)) {
        foreach ($Valid as $fname) $validate_response[$fname] = "";
      }
      return $validate_response;
    }

    public function head() {
      print is_callable($this->header) ? ($this->header)($this) : $this->header;
    }

    public function foot() {
      print is_callable($this->footer) ? ($this->footer)($this) : $this->footer;
    }

    public function wrap($content) {
      $this->head();
      print $content;
      $this->foot();
    }

    public function set_head($header) {
      $this->header = $header;
    }

    public function set_foot($footer) {
      $this->footer = $footer;
    }

    public function display_form() {
      $_SESSION['start_timestamp'] = time();
      $this->head();
      print $this->tag("form", [
        "id" => "af-form",
        "name" => $this->form_name,
        "action" => $_SERVER['SCRIPT_NAME'],
        "method" => $this->method
      ]);
      foreach ($this->fields as $Field) {
        if ($Field['type'] == "html") {
          if ($Field['page'] == $this->Input['af_page']) {
            print is_callable($Field['html']) ? $Field['html']() : "$Field[html]\n";
          }
        }
        else {
          $labeled_by = "";
          if ($Field['page'] != $this->Input['af_page']) {
            if ($Field['type'] == "multiselect" || ($Field['type'] == "checkbox" && count($Field['values']) > 1)) {
              print $this->tag("select", [
                "multiple" => true,
                "name" => $Field['field_name']."[]",
                "style" => "display: none;"
              ]);
              if (is_array($this->Input[$Field['field_name']] ?? "")) {
                foreach ($this->Input[$Field['field_name']] as $val) {
                  print "  ".$this->tag("option", ["value" => $val], "");
                }
              }
              print "</select>\n";
            }
            else {
              print $this->tag("input", [
                "type" => "hidden",
                "name" => $Field['field_name']
              ]);
            }
          }
          else {
            if (!in_array($Field['type'], ["radio", "checkbox"])) {
              print "  <af-field>\n";
              print "    ".$this->tag("label", [
                "for" => "af-$Field[field_name]"
              ], $this->sanitize($Field['label']));
            }
            else {
              print "    ".$this->tag("af-field", [
                "role" => ($Field['type'] == "radio" ? "radio" : "")."group",
                "labeled-by" => "af-label-$Field[field_name]"
              ]);
              print "    ".$this->tag(
                "label",
                ["id" => "af-label-$Field[field_name]"],
                $this->sanitize($Field['label'])
              );
            }
            print "    <af-value>\n";
            if (in_array($Field['type'], ["select", "multiselect"])) {
              [$brax, $mult] = $Field['type'] == "multiselect" ? ["[]", true] : ["", NULL];
              print "      ".$this->tag("select", [
                "id" => "af-$Field[field_name]",
                "name" => "$Field[field_name]$brax",
                "multiple" => $mult
              ] + $Field['attributes']);
              foreach ($Field['values'] as $val => $label) {
                print "        ".$this->tag("option", ["value" => $val], $this->sanitize($label));
              }
              print "      </select>\n";
            }
            else if (in_array($Field['type'], ["checkbox", "radio"])) {
              $vid = 0;
              $brax = "";
              if ($Field['type'] == "checkbox" && count($Field['values']) > 1) $brax = "[]";
              foreach ($Field['values'] as $val => $label) {
                print "      ".$this->tag("input", [
                  "id" => "af-$Field[field_name]-$vid",
                  "type" => $Field['type'],
                  "name" => "$Field[field_name]$brax",
                  "value" => $val
                ] + $Field['attributes']);
                print "      ".$this->tag("label", [
                  "for" => "af-$Field[field_name]-$vid"
                ], " ".$this->sanitize($label));
                $vid++;
              }
            }
            else if ($Field['type'] == "textarea") {
              print    "      ".$this->tag("textarea", [
                "id" => "af-$Field[field_name]",
                "name" => $Field['field_name']
              ] + $Field['attributes'], "");
            }
            else {
              print "      ".$this->tag("input", [
                "type" => $Field['type'],
                "id" => "af-$Field[field_name]",
                "name" => $Field['field_name'],
                "list" => !empty($Field['values']) ? "af-list-$Field[field_name]" : NULL
              ] + $Field['attributes']);
              if (!empty($Field['values'])) {
                print "      ".$this->tag("datalist", ["id" => "af-list-$Field[field_name]"]);
                foreach ($Field['values'] as $key => $val) {
                  print "        ".$this->tag("option", ["value" => $val]);
                }
                print "      </datalist>\n";
              }
            }
            print "      ".$this->tag("af-error", ["id" => "af-err-$Field[field_name]"], "");
            print "    </af-value>\n";
            print "  </af-field>\n";
          }
        }
      }
      print "  ".$this->tag("af-error", ["id" => "af-err-other-errs"], "");
      print "  ".$this->tag("input", [
        "type" => "hidden",
        "name" => "af_action",
        "value" => hash("sha256", "continue$_SESSION[start_timestamp]")
      ]);
      print "  ".$this->tag("input", [
        "type" => "hidden",
        "name" => "af_page"
      ]);
      print "  ".$this->tag("input", [
        "type" => "button",
        "id" => "af-submit-button",
        "value" => $this->submit_label
      ]);
      print "</form>\n";

      print "<script>\n";
      if ($this->form_name != "form1") print '  AF_FORM_NAME = "'.$this->form_name.'";'."\n";
      $Rpop = ["af_page" => $this->Input['af_page']];
      foreach ($this->fields as $Field) {
        if (!isset($Field['field_name'])) continue;
        $Rpop[$Field['field_name']] = $this->Input[$Field['field_name']] ?? (
          ($this->Input['af_action'] ?? "") == hash("sha256", "continue$_SESSION[start_timestamp]") ? "" : $Field['default']
        );
      }
      unset($Rpop['af_action']);
      print "let AF_FIELDS = ".json_encode($Rpop).";\n";
      print "af_repop_form(AF_FIELDS);\n";
      print "let af_ajax_action = \"".hash("sha256", "ajax$_SESSION[start_timestamp]")."\";\n";
      print "af_init(".($this->ajax ? "true" : "false").", ".($this->ajax_as_you_go ? "true" : "false").");\n";
      if (!empty($this->errors)) print "af_show_errors(".json_encode($this->errors).");\n";
      print "</script>\n";
      $this->foot();
    }

    public function add_fields($Arr = []) {
      foreach ($Arr as $field_name => $Info) {
        if (is_string($Info) || is_callable($Info)) $this->add_html($Info);
        else $this->add_field($field_name, $Info['type'] ?? "text", $Info);
      }
      return true;
    }

    public function add_field($field_name, $type = "text", $label = NULL, $validate = false, $values = [], $default = "", $Attributes = []) {
      if (str_contains($field_name, "/")) [$type, $field_name] = explode("/", $field_name, 2);
      if (is_array($label)) {
        $Info = $label;
        $label = $Info['label'] ?? NULL;
        $validate = $Info['validate'] ?? $validate;
        $values = $Info['values'] ?? $values;
        $default = $Info['default'] ?? $default;
        $Attributes = $Info['attributes'] ?? $Attributes;
        foreach (["placeholder", "min", "max", "size", "maxlength", "pattern", "step", "autocomplete",
                  "autofocus", "required", "readonly", "disabled"] as $att_field) {
          if (!empty($Info[$att_field])) $Attributes[$att_field] = $Info[$att_field];
        }
        unset($Info);
      }
      if (in_array($field_name, ["af_action", "af_page", "action", "other-errs"])) {
        return $this->warn("create_field: reserved field name ($field_name). Please use a different name");
      }
      else if (!preg_match('/^[a-z_]+[0-9a-z\-_\.:]*$/i', $field_name)) {
        return $this->warn("create_field: invalid field name ($field_name)");
      }
      if (!in_array($type, [
        "text", "number", "date", "email", "search", "tel", "select", "multiselect", "radio",
        "checkbox", "textarea", "color", "file", "search", "range", "url",
        "time", "date", "week", "month", "datetime-local"
      ])) {
        return $this->warn("create_field: invalid type ($field_name)");
      }
      $this->fields[] = [
        "field_name" => $field_name,
        "label" => $label === NULL ? $field_name : $label,
        "type" => $type,
        "validate" => $validate,
        "default" => $default,
        "values" => $values,
        "attributes" => $Attributes,
        "page" => $this->pages
      ];
      if ($type == "file") {
        if ($this->method != "POST") return $this->warn("GET method is incompatible with file inputs");
        $this->enctype = "multipart/form-data";
      }
      return true;
    }

    public function new_page() {
      $this->pages++;
    }

    public function add_select($field_name, $label = "", $validate = false, $values = [], $default = "", $Attributes = []) {
      $this->add_field($field_name, "select", $label, $validate, $values, $default, $Attributes);
    }

    public function add_text($field_name, $label = "", $validate = false, $values = [], $default = "", $Attributes = []) {
      $this->add_field($field_name, "text", $label, $validate, $values, $default, $Attributes);
    }
    
    public function add_textarea($field_name, $label = "", $validate = false, $values = [], $default = "", $Attributes = []) {
      $this->add_field($field_name, "textarea", $label, $validate, $values, $default, $Attributes);
    }

    public function add_radio($field_name, $label = "", $validate = false, $values = [], $default = "", $Attributes = []) {
      $this->add_field($field_name, "radio", $label, $validate, $values, $default, $Attributes);
    }

    public function add_checkbox($field_name, $label = "", $validate = false, $values = [], $default = "", $Attributes = []) {
      $this->add_field($field_name, "checkbox", $label, $validate, $values, $default, $Attributes);
    }

    public function add_html($html) {
      $this->fields[] = [
        "type" => "html",
        "html" => $html,
        "page" => $this->pages
      ];
    }

    public function add_validation($field_name, $validate) {
      foreach ($this->fields as $fid => $Field) {
        if (($Field['field_name'] ?? "") === $field_name) {
          $this->fields[$fid]['validate'] = $validate;
          return true;
        }
      }
      return $this->warn("add_validation: field not found ($field_name)");
    }
    
    public function att($Attribs = []) {
      $att_string = "";
      foreach ($Attribs as $attrib => $val) {
        if (is_null($val)) continue;
        if (in_array($attrib, ["disabled", "autofocus", "multiple", "required", "disabled", "readonly"]) && $val) {
          $att_string .= " $attrib";
        }
        else $att_string .= " ".$attrib.'="'.$this->sanitize($val).'"';
      }
      return $att_string;
    }

    public function tag($tag_name, $Attribs = [], $close_after = NULL, $xtra = "") {
      $tag_string = "<$tag_name".$this->att($Attribs)."$xtra>";
      if (!is_null($close_after)) $tag_string .= "$close_after</$tag_name>";
      return "$tag_string\n";
    }

    public function inp_set($var, $val) {
      $this->Input[$var] = $val;
      $this->Sanitized[$var] = $this->sanitize($val);
    }

    public function inp_unset($var) {
      unset($this->Input[$var], $this->Sanitized[$var]);
    }

    private function warn($message) {
      trigger_error($message, E_USER_WARNING);
      return $this->form_valid = false;
    }

    public $Province_List = [
      "AB" => "Alberta",
      "BC" => "British Columbia",
      "MB" => "Manitoba",
      "NB" => "New Brunswick",
      "NL" => "Newfoundland/Labrador",
      "NS" => "Nova Scotia",
      "NT" => "Northwest Territories",
      "NU" => "Nunavut",
      "ON" => "Ontario",
      "PE" => "Prince Edward Island",
      "QC" => "Quebec",
      "SK" => "Saskatchewan",
      "YT" => "Yukon"
    ];

    public $State_List = [
      "AK" => "Alaska",
      "AL" => "Alabama",
      "AR" => "Arkansas",
      "AS" => "American Samoa",
      "AZ" => "Arizona",
      "CA" => "California",
      "CO" => "Colorado",
      "CT" => "Connecticut",
      "DC" => "District of Columbia",
      "DE" => "Delaware",
      "FL" => "Florida",
      "FM" => "Micronesia",
      "GA" => "Georgia",
      "GU" => "Guam",
      "HI" => "Hawaii",
      "IA" => "Iowa",
      "ID" => "Idaho",
      "IL" => "Illinois",
      "IN" => "Indiana",
      "KS" => "Kansas",
      "KY" => "Kentucky",
      "LA" => "Louisiana",
      "MA" => "Massachusetts",
      "MD" => "Maryland",
      "ME" => "Maine",
      "MI" => "Michigan",
      "MN" => "Minnesota",
      "MO" => "Missouri",
      "MP" => "Northern Marianas",
      "MS" => "Mississippi",
      "MT" => "Montana",
      "NC" => "North Carolina",
      "ND" => "North Dakota",
      "NE" => "Nebraska",
      "NH" => "New Hampshire",
      "NJ" => "New Jersey",
      "NM" => "New Mexico",
      "NV" => "Nevada",
      "NY" => "New York",
      "OH" => "Ohio",
      "OK" => "Oklahoma",
      "OR" => "Oregon",
      "PA" => "Pennsylvania",
      "PR" => "Puerto Rico",
      "RI" => "Rhode Island",
      "SC" => "South Carolina",
      "SD" => "South Dakota",
      "TN" => "Tennessee",
      "TX" => "Texas",
      "UT" => "Utah",
      "VA" => "Virginia",
      "VI" => "Virgin Islands",
      "VT" => "Vermont",
      "WA" => "Washington",
      "WI" => "Wisconsin",
      "WV" => "West Virginia",
      "WY" => "Wyoming"
    ];

    public $Country_List = [
      "AF" => "Afghanistan",
      "AL" => "Albania",
      "DZ" => "Algeria",
      "AS" => "American Samoa",
      "AD" => "Andorra",
      "AO" => "Angola",
      "AI" => "Anguilla",
      "AQ" => "Antarctica",
      "AG" => "Antigua and Barbuda",
      "AR" => "Argentina",
      "AM" => "Armenia",
      "AW" => "Aruba",
      "AU" => "Australia",
      "AT" => "Austria",
      "AZ" => "Azerbaijan",
      "BS" => "Bahamas",
      "BH" => "Bahrain",
      "BD" => "Bangladesh",
      "BB" => "Barbados",
      "BY" => "Belarus",
      "BE" => "Belgium",
      "BZ" => "Belize",
      "BJ" => "Benin",
      "BM" => "Bermuda",
      "BT" => "Bhutan",
      "BO" => "Bolivia",
      "BA" => "Bosnia Hercegovina",
      "BW" => "Botswana",
      "BV" => "Bouvet Island",
      "BR" => "Brazil",
      "BN" => "Brunei Darussalam",
      "BG" => "Bulgaria",
      "BF" => "Burkina Faso",
      "BI" => "Burundi",
      "KH" => "Cambodia",
      "CM" => "Cameroon",
      "CA" => "Canada",
      "CV" => "Cape Verde",
      "KY" => "Cayman Islands",
      "CF" => "Central African Republic",
      "TD" => "Chad",
      "CL" => "Chile",
      "CN" => "China",
      "CX" => "Christmas Island",
      "CC" => "Cocos (Keeling) Islands",
      "CO" => "Colombia",
      "KM" => "Comoros",
      "CG" => "Congo",
      "CK" => "Cook Islands",
      "CR" => "Costa Rica",
      "CI" => "Cote D'ivoire",
      "HR" => "Croatia",
      "CU" => "Cuba",
      "CY" => "Cyprus",
      "CZ" => "Czech Republic",
      "DK" => "Denmark",
      "DJ" => "Djibouti",
      "DM" => "Dominica",
      "DO" => "Dominican Republic",
      "TP" => "East Timor",
      "EC" => "Ecuador",
      "EG" => "Egypt",
      "SV" => "El Salvador",
      "GQ" => "Equatorial Guinea",
      "ER" => "Eritrea",
      "EE" => "Estonia",
      "ET" => "Ethiopia",
      "FK" => "Falkland Islands (Malvinas)",
      "FO" => "Faroe Islands",
      "FJ" => "Fiji",
      "FI" => "Finland",
      "FR" => "France",
      "GF" => "French Guiana",
      "PF" => "French Polynesia",
      "TF" => "French Southern Territories",
      "GA" => "Gabon",
      "GM" => "Gambia",
      "GE" => "Georgia",
      "DE" => "Germany",
      "GH" => "Ghana",
      "GI" => "Gibraltar",
      "GR" => "Greece",
      "GL" => "Greenland",
      "GD" => "Grenada",
      "GP" => "Guadeloupe",
      "GU" => "Guam",
      "GT" => "Guatemala",
      "GN" => "Guinea",
      "GW" => "Guinea-Bissau",
      "GY" => "Guyana",
      "HT" => "Haiti",
      "HM" => "Heard and McDonald Islands",
      "HN" => "Honduras",
      "HK" => "Hong Kong",
      "HU" => "Hungary",
      "IS" => "Iceland",
      "IN" => "India",
      "ID" => "Indonesia",
      "IR" => "Iran (Islamic Republic of)",
      "IQ" => "Iraq",
      "IE" => "Ireland",
      "IL" => "Israel",
      "IT" => "Italy",
      "JM" => "Jamaica",
      "JP" => "Japan",
      "JO" => "Jordan",
      "KZ" => "Kazakhstan",
      "KE" => "Kenya",
      "KI" => "Kiribati",
      "KP" => "Korea, DPRK",
      "KR" => "Korea, Republic of",
      "KW" => "Kuwait",
      "KG" => "Kyrgyzstan",
      "LV" => "Latvia",
      "LB" => "Lebanon",
      "LS" => "Lesotho",
      "LR" => "Liberia",
      "LY" => "Libyan Arab Jamahiriya",
      "LI" => "Liechtenstein",
      "LT" => "Lithuania",
      "LU" => "Luxembourg",
      "MO" => "Macau",
      "MG" => "Madagascar",
      "MW" => "Malawi",
      "MY" => "Malaysia",
      "MV" => "Maldives",
      "ML" => "Mali",
      "MT" => "Malta",
      "MH" => "Marshall Islands",
      "MQ" => "Martinique",
      "MR" => "Mauritania",
      "MU" => "Mauritius",
      "YT" => "Mayotte",
      "MX" => "Mexico",
      "FM" => "Micronesia",
      "MD" => "Moldova, Republic of",
      "MC" => "Monaco",
      "MN" => "Mongolia",
      "MS" => "Montserrat",
      "MA" => "Morocco",
      "MZ" => "Mozambique",
      "MM" => "Myanmar",
      "NA" => "Namibia",
      "NR" => "Nauru",
      "NP" => "Nepal",
      "NL" => "Netherlands",
      "AN" => "Netherlands Antilles",
      "NC" => "New Caledonia",
      "NZ" => "New Zealand",
      "NI" => "Nicaragua",
      "NE" => "Niger",
      "NG" => "Nigeria",
      "NU" => "Niue",
      "NF" => "Norfolk Island",
      "MP" => "Northern Mariana Islands",
      "NO" => "Norway",
      "OM" => "Oman",
      "PK" => "Pakistan",
      "PW" => "Palau",
      "PA" => "Panama",
      "PG" => "Papua New Guinea",
      "PY" => "Paraguay",
      "PE" => "Peru",
      "PH" => "Philippines",
      "PN" => "Pitcairn",
      "PL" => "Poland",
      "PT" => "Portugal",
      "PR" => "Puerto Rico",
      "QA" => "Qatar",
      "RE" => "Reunion",
      "RO" => "Romania",
      "RU" => "Russian Federation",
      "RW" => "Rwanda",
      "KN" => "Saint Kitts and Nevis",
      "LC" => "Saint Lucia",
      "VC" => "Saint Vincent and The Grenadines",
      "WS" => "Samoa",
      "SM" => "San Marino",
      "ST" => "Sao Tome and Principe",
      "SA" => "Saudi Arabia",
      "SN" => "Senegal",
      "SC" => "Seychelles",
      "SL" => "Sierra Leone",
      "SG" => "Singapore",
      "SK" => "Slovakia",
      "SI" => "Slovenia",
      "SB" => "Solomon Islands",
      "SO" => "Somalia",
      "ZA" => "South Africa",
      "GS" => "South Georgia",
      "ES" => "Spain",
      "LK" => "Sri Lanka",
      "SH" => "St. Helena",
      "PM" => "St. Pierre and Miquelon",
      "SD" => "Sudan",
      "SR" => "Suriname",
      "SJ" => "Svalbard/Jan Mayen Islands",
      "SZ" => "Swaziland",
      "SE" => "Sweden",
      "CH" => "Switzerland",
      "SY" => "Syrian Arab Republic",
      "TW" => "Taiwan",
      "TJ" => "Tajikistan",
      "TZ" => "Tanzania",
      "TH" => "Thailand",
      "TG" => "Togo",
      "TK" => "Tokelau",
      "TO" => "Tonga",
      "TT" => "Trinidad and Tobago",
      "TN" => "Tunisia",
      "TR" => "Turkey",
      "TM" => "Turkmenistan",
      "TC" => "Turks and Caicos Islands",
      "TV" => "Tuvalu",
      "UG" => "Uganda",
      "UA" => "Ukraine",
      "AE" => "United Arab Emirates",
      "GB" => "United Kingdom",
      "US" => "United States",
      "UY" => "Uruguay",
      "UZ" => "Uzbekistan",
      "VU" => "Vanuatu",
      "VA" => "Vatican City",
      "VE" => "Venezuela",
      "VN" => "Viet Nam",
      "VG" => "Virgin Islands (British)",
      "VI" => "Virgin Islands (US)",
      "WF" => "Wallis and Futuna Islands",
      "EH" => "Western Sahara",
      "YE" => "Yemen, Republic of",
      "YU" => "Yugoslavia",
      "ZM" => "Zambia",
      "ZW" => "Zimbabwe"
    ];
  }
