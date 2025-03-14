<?php
  # AutoForm by Mark Hall
  # https://abstract-productions.net

  if (!isset($_SESSION)) session_start();

  class AutoForm {
    public $header;
    public $footer;
    public $input;
    public $sanitized;
    
    public $form_name;
    public $method;
    public $submit_label = "Submit";
    public $submit_function;
    public $fields = [];
    public $errors = [];

    public $form_valid = true;

    public function __construct($form_name = "form1", $method = "POST") {
      $this->method = $method;
      $this->input = $this->method == "POST" ? $_POST : $_GET;
      $this->sanitized = $this->sanitize($this->input);
      $this->form_name = "form1";
    }

    public function sanitize($Input) {
      if (is_array($Input)) {
        foreach ($Input as $key => $value) {
          $Input[$key] = $this->sanitize($value);
        }
      }
      else return htmlspecialchars($Input);
    }

    public function execute() {
      if (!$this->form_valid) die("ERROR: Invalid form settings");

      foreach ($this->fields as $Field) {
        if (isset($Field['default'])) {
          if (!isset($this->input[$Field['field_name']])) {
            $this->input[$Field['field_name']] = $Field['default'];
          }
        }
      }

      if (($this->input['act'] ?? "") == "continue") {
        $this->errors = $this->validate();
        if (empty($this->errors)) {
          if (empty($this->submit_function)) {
            error_log("submit_function is missing");
            return false;
          }
          else if (is_string($this->submit_function)) {
            require $this->submit_function;
            return true;
          }
          else return ($this->submit_function)($this);
        }
      }
      return $this->display_form();
    }

    public function validate($up_to = "") {
      $validate_response = [];
      foreach ($this->fields as $Field) {
        if ($Field['type'] == "html") continue;
        $res = "";
        
        if (is_callable($Field['validate'])) {
          $res = $Field['validate']($this->input[$Field['field_name']] ?? "");
        }
        else if ($Field['validate']) {
          if (is_array($Field['validate'])) {
            foreach ($Field['validate'] as $v_type => $Values) {
              if ($v_type == "default") {
                $res = $Values;
                break;
              }
              foreach ($Values as $val => $val_res) {
                if ($v_type == "regex") {
                  if (preg_match($val, $this->input[$Field['field_name']] ?? "")) {
                    $res = $val_res;
                    break 2;
                  }
                }
                else if (($this->input[$Field['field_name']] ?? "") == $val) {
                  $res = $val_res;
                  break 2;
                }
              }
            }
          }
          else if (($this->input[$Field['field_name']] ?? "") === "") {
            if (is_string($Field['validate'])) $res = $Field['validate'];
            else $res = 'Field "'.($Field['label'] ?: $Field['field_name']).'" is missing';
          }
        }
        if ($res) $validate_response[$Field['field_name']] = $res;
      }
      return $validate_response;
    }

    public function display_form() {
      print $this->header;
      print '<form name="'.$this->form_name.'" action="'.$_SERVER['SCRIPT_NAME'].'" method="'.$this->method.'">';
      foreach ($this->fields as $fid => $Field) {
        if ($Field['type'] == "html") print "$Field[html]\n";
        else {
          print "  <af-field>\n";
          if (!in_array($Field['type'], ["radio", "checkbox"])) {
            print "    <label for=\"af-$fid\">$Field[label]</label>\n";
          }
          else print "   <label>$Field[label]</label>\n";
          print "    <af-value>\n";
          if (in_array($Field['type'], ["select", "multiselect"])) {
            [$brax, $mult] = $Field['type'] == "multiselect" ? ["[]", " multiple"] : ["", ""];
            print "      <select id=\"af-$fid\" name=\"$Field[field_name]$brax\"$mult>\n";
            foreach ($Field['vals'] as $val => $label) {
              print "        <option value=\"$val\">$label</option>\n";
            }
            print "      </select>\n";
          }
          else if (in_array($Field['type'], ["checkbox", "radio"])) {
            $vid = 0;
            $brax = "";
            if ($Field['type'] == "checkbox" && count($Field['vals']) > 1) $brax = "[]";
            foreach ($Field['vals'] as $val => $label) {
              print "    <input id=\"af-$fid-$vid\" type=\"$Field[type]\" name=\"$Field[field_name]$brax\" value=\"$val\">";
              print "<label for=\"af-$fid-$vid\"> $label</label>\n";
              $vid++;
            }
          }
          else print "      <input type=\"$Field[type]\" id=\"af-$fid\" name=\"$Field[field_name]\">\n";
          print "      <af-error>".($this->errors[$Field['field_name']] ?? "")."</af-error>\n";
          print "    </af-value>\n";
          print "  </af-field>\n";
        }
      }
      print '  <input type="hidden" name="act" value="continue">'."\n";
      print '  <input type="button" onclick="af_submit_form();" value="'.$this->submit_label.'">'."\n";
      print "</form>\n";

      print "<script>\n";
      if ($this->form_name != "form1") print '  AF_FORM_NAME = "'.$this->form_name.'";'."\n";
      if (!empty($this->input)) {
        $Rpop = $this->input;
        unset($Rpop['act']);
        print "repop_form(".json_encode($Rpop).");\n";
      }
      print "</script>\n";
      print $this->footer;
      return true;
    }

    public function add_fields($Arr = []) {
      foreach ($Arr as $field_name => $Info) {
        if (is_string($Info)) $this->add_html($Info);
        else $this->add_field(
          $field_name,
          $Info['type'] ?? "text",
          $Info['label'] ?? NULL,
          $Info['validate'] ?? false,
          $Info['vals'] ?? [],
          $Info['default'] ?? ""
        );
      }
      return true;
    }

    public function add_field($field_name, $type = "text", $label = NULL, $validate = false, $vals = [], $default = "") {
      if (str_contains($field_name, ":")) [$type, $field_name] = explode(":", $field_name);
      if (is_numeric($field_name) || $field_name === "") {
        error_log("create_field: invalid field name ($field_name)");
        return $this->form_valid = false;
      }
      if (!in_array($type, [
        "text", "number", "date", "email", "search", "tel", "select", "multiselect", "radio", "checkbox"
      ])) {
        error_log("create_field: invalid type ($field_name)");
        return $this->form_valid = false;
      }
      $this->fields[] = [
        "field_name" => $field_name,
        "label" => $label === NULL ? $field_name : $label,
        "type" => $type,
        "validate" => $validate,
        "default" => $default,
        "vals" => $vals
      ];
      return true;
    }

    public function add_select($field_name, $label = "", $validate = false, $vals = [], $default = "") {
      $this->add_field($field_name, "select", $label, $validate, $vals, $default);
    }

    public function add_text($field_name, $label = "", $validate = false, $vals = [], $default = "") {
      $this->add_field($field_name, "text", $label, $validate, $vals, $default);
    }

    public function add_radio($field_name, $label = "", $validate = false, $vals = [], $default = "") {
      $this->add_field($field_name, "radio", $label, $validate, $vals, $default);
    }

    public function add_checkbox($field_name, $label = "", $validate = false, $vals = [], $default = "") {
      $this->add_field($field_name, "checkbox", $label, $validate, $vals, $default);
    }

    public function add_html($html) {
      $this->fields[] = [
        "type" => "html",
        "html" => $html
      ];
    }
  }
