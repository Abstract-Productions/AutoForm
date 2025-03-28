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
    public $fields = [];
    public $errors = [];
    private $robot_delay;

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
    }

    public function sanitize($Input) {
      if (!is_array($Input)) return htmlspecialchars($Input);
      foreach ($Input as $key => $value) $Input[$key] = $this->sanitize($value);
      return $Input;
    }

    public function execute($Fields = []) {
      if (!empty($Fields)) $this->add_fields($Fields);
      if (!$this->form_valid) die("ERROR: Invalid form settings");

      if (in_array($this->Input['af_action'] ?? "", [hash("sha256", "continue$_SESSION[start_timestamp]"), "ajax"])) {
        $this->errors = $this->validate($this->Input['af_up_to'] ?? "");
        if ($this->Input['af_action'] == "ajax") {
          print empty($this->errors) ? "OK" : json_encode($this->errors);
          return false;
        }
        if (empty($this->errors)) return true;
      }
      $this->display_form();
      return false;
    }

    public function validate($up_to = "") {
      $validate_response = [];
      foreach ($this->fields as $Field) {
        if ($Field['type'] == "html") continue;
        $res = "";
        
        if (is_callable($Field['validate'])) {
          $res = $Field['validate']($this->Input[$Field['field_name']] ?? "");
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
                "date" => '/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/'
              ];
              if (in_array($v_type, array_keys($pregs))) {
                if (!preg_match($pregs[$v_type], $check)) {
                  $res = $Values;
                  break;
                }
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
        if ($Field['field_name'] == $up_to) return $validate_response;
      }
      $delay = time() - $_SESSION['start_timestamp'];
      if ($delay < $this->robot_delay || $delay > 3600) {
        $validate_response['other-errs'] = "Possible robot detected";
      }
      return $validate_response;
    }

    public function head() {
      print is_callable($this->header) ? ($this->header)($this) : $this->header;
    }

    public function foot() {
      print is_callable($this->footer) ? ($this->footer)($this) : $this->footer;
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
        if ($Field['type'] == "html") print is_callable($Field['html']) ? $Field['html']() : "$Field[html]\n";
        else {
          $labeled_by = "";
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
            [$brax, $mult] = $Field['type'] == "multiselect" ? ["[]", " multiple"] : ["", ""];
            print "      ".$this->tag("select", [
              "id" => "af-$Field[field_name]",
              "name" => "$Field[field_name]$brax"
            ], NULL, $mult);
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
              ]);
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
            ], "");
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
      print "  ".$this->tag("af-error", ["id" => "af-err-other-errs"], "");
      print "  ".$this->tag("input", [
        "type" => "hidden",
        "name" => "af_action",
        "value" => hash("sha256", "continue$_SESSION[start_timestamp]")
      ]);
      print "  ".$this->tag("input", [
        "type" => "button",
        "id" => "af-submit-button",
        "value" => $this->submit_label
      ]);
      print "</form>\n";

      print "<script>\n";
      if ($this->form_name != "form1") print '  AF_FORM_NAME = "'.$this->form_name.'";'."\n";
      $Rpop = [];
      foreach ($this->fields as $Field) {
        if (!isset($Field['field_name'])) continue;
        $Rpop[$Field['field_name']] = $this->Input[$Field['field_name']] ?? (
          ($this->Input['af_action'] ?? "") == hash("sha256", "continue$_SESSION[start_timestamp]") ? "" : $Field['default']
        );
      }
      unset($Rpop['af_action']);
      print "let AF_FIELDS = ".json_encode($Rpop).";\n";
      print "af_repop_form(AF_FIELDS);\n";
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
        "attributes" => $Attributes
      ];
      if ($type == "file") {
        if ($this->method != "POST") return $this->warn("GET method is incompatible with file inputs");
        $this->enctype = "multipart/form-data";
      }
      return true;
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
        "html" => $html
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
        $att_string .= " ".$attrib.'="'.$this->sanitize($val).'"';
      }
      return $att_string;
    }

    public function tag($tag_name, $Attribs = [], $close_after = NULL, $xtra = "") {
      $tag_string = "<$tag_name".$this->att($Attribs)."$xtra>";
      if (!is_null($close_after)) $tag_string .= "$close_after</$tag_name>";
      return "$tag_string\n";
    }

    private function warn($message) {
      trigger_error($message, E_USER_WARNING);
      return $this->form_valid = false;
    }
  }
