// Autoform
// By Mark Hall
// https://abstract-productions.net
"use strict";

// Default form name is form1 but can change it after including autoform.js
let AF_FORM_NAME = "form1", AF_select_label = "", AF_field_list = [];

for (let lmnt of ["af-field", "af-value", "af-error"])
  customElements.define(lmnt, class extends HTMLElement {});

// Shortcut to set options in a DOM element and/or append it to another element
function af_set_elops(elem, elops, appendto = "", children = []) {
  if (af_is_el(elops)) [elops, appendto] = [{}, elops];
  if (af_is_arr(elops)) [elops, children] = [{}, elops];
  if (af_is_arr(appendto)) [appendto, children] = [af_is_arr(children) ? "" : children, appendto];
  for (let i in elops) typeof elem[i] === "function" ?
    Object.keys(elops[i]).map(j => elem[i](j, elops[i][j])) :
    i.match(/\./) ? elem[i.split(/\./)[0]][i.split(/\./)[1]] = elops[i] : elem[i] = elops[i];
  for (let i of af_is_arr(children) ? children : [children]) elem.appendChild(i);
  return appendto ? af_get_elem(appendto).appendChild(elem) : elem;
}

function af_set_param(id, param, val) {
  let results = {};
  if (af_is_obj(id))
    for (let i in id) results[i] = af_set_param(i, id[i]);
  else if (af_is_obj(param))
    for (let i in param) results[i] = af_set_param(id, i, param[i]);
  else {
    let elm;
    if (!af_is_el(id) && id.match(/[\.#>\[\*\+: \(,~\|]/)) {
      results = val;
      for (let e of af_qs(id)) af_set_elops(e, {[param]: val});
    }
    else {
      elm = af_el(id) || document.forms[AF_FORM_NAME][id];
      if (elm === undefined) return console.error(`af_set_param: no element found for ID ${id}`);
      af_set_elops(elm, {[param]: val});
      return val;
    }
  }
  return results;
}

function af_el() {
  let returnme = [];
  for (let i of arguments) returnme.push(af_is_el(i) ? i : document.getElementById(i));
  return returnme.length > 1 ? returnme : returnme[0];
}

function af_get_elem() {
  let returnme = [];
  for (let i of arguments) 
    returnme.push(af_is_el(i) ? i : i.charAt(0) == "#" ? af_el(i.substr(1)) : af_el(i) ? af_el(i) : document.forms[AF_FORM_NAME] ? document.forms[AF_FORM_NAME][i] || null : null);
  return returnme.length > 1 ? returnme : returnme[0];
}

var af_qs = i => i instanceof NodeList ? i : document.querySelectorAll(i),
af_is_obj = i => typeof i === "object" && i !== null && !af_is_el(i),
af_is_arr = i => i instanceof Array,
af_is_el = i => i instanceof Element,
af_build_query = params => af_is_obj(params) ? Object.keys(params).map(key => af_is_arr(params[key]) ? params[key].map(val => encodeURIComponent(key.replace("[]", "") + "[]") + "=" + encodeURIComponent(val)).join("&") : encodeURIComponent(key) + "=" + encodeURIComponent(params[key])).join("&") : params,
af_new_el = (elname, elops = {}, appendto = "", children = []) => af_set_elops(document.createElement(elname), elops, appendto, children);

// Get value from whatever kind of field it's in
function af_get_val(fname) {
  if (af_is_arr(fname)) {
    let results = {};
    fname.forEach(fn => results[fn] = af_get_val(fn));
    return results;
  }
  if (af_is_obj(fname)) {
    for (let fn in fname) fname[fn] = af_get_val(fn);
    return fname;
  }

  let field_obj = af_get_elem(fname);
  if (field_obj === null) return undefined;
  let ft = field_obj.type;

  if (ft && ft.match(/^select-(one|multiple)$/g)) {
    if (field_obj.multiple) {
      AF_select_label = [];
      let return_arr = [];
      for (let x = 0; x < field_obj.length; x++) {
        if (field_obj.options[x].selected) {
          return_arr.push(field_obj.options[x].value);
          AF_select_label.push(field_obj.options[x].text);
        }
      }
      return return_arr;
    }
    if (field_obj.options.length == 0 || field_obj.selectedIndex == -1) return AF_select_label = "";
    AF_select_label = field_obj[field_obj.selectedIndex].text;
    return field_obj.value;
  }

  if (field_obj.length > 1 && field_obj[0].type == "checkbox") {
    let return_arr = [], count = 0;
    for (let x = 0; x < field_obj.length; x++)
      if (field_obj[x].checked) return_arr[count++] = field_obj[x].value;
    return return_arr;
  }
  if (field_obj.length > 1 || ft == "radio") {
    if (field_obj.length == undefined) return field_obj.checked ? field_obj.value : "";
    for (let x = 0; x < field_obj.length; x++)
      if (field_obj[x].checked) return field_obj[x].value;
    return "";
  }
  if (ft != "checkbox" || field_obj.checked) {
    if (field_obj.value === undefined) console.error(`af_set_val: no value atribute for ${fname}`);
    return field_obj.value;
  }
  return "";
}

// Set value in whatever kind of field it's in
function af_set_val(fname, new_val) {
  if (af_is_obj(fname)) {
    let results = {};
    for (let x in fname) {
      results[x] = af_set_val(x, fname[x]);
      if (results[x] === undefined) delete results[x];
    }
    return results;
  }
  let field_obj = af_get_elem(fname);
  if (field_obj === null) return console.error(`af_set_val: ID or field ${fname} undefined`);
  let ft = field_obj.type;

  if (ft && ft.match(/^select-(one|multiple)$/g)) {
    if (field_obj.multiple)
      for (let x = 0; x < field_obj.length; x++) field_obj.options[x].selected = new_val.includes(field_obj[x].value);
    else field_obj.value = new_val;
  }
  else if (field_obj.length > 1 && field_obj[0].type == "checkbox")
    for (let x = 0; x < field_obj.length; x++) field_obj[x].checked = new_val.includes(field_obj[x].value);
  else if (field_obj.length == 2 && field_obj[0].type == "hidden" && field_obj[1].type != "radio" && field_obj[1].type != "checkbox") field_obj[1].value = new_val;
  else if (field_obj.length > 1 || ft == "radio") {
    new_val = new_val.toString();
    if (field_obj.length == undefined) field_obj.checked = field_obj.value == new_val;
    else for (let x = 0; x < field_obj.length; x++) field_obj[x].checked = field_obj[x].value == new_val;
  }
  else if (ft == "checkbox") field_obj.checked = field_obj.value == new_val;
  else field_obj.value = new_val;
  return new_val;
}

// Return AJAX info (POST)
function af_post_ajax(fname, post_vars = []) {
  return new Promise((ok, bad) => {
    fetch(fname, {
      method: "POST",
      body: af_build_query(post_vars),
      headers: {"Content-Type": "application/x-www-form-urlencoded"}
    }).then(response => response.status != 200 ? bad(console.error(`af_post_ajax: no response from ${fname}`))
      : response.text().then(text => ok(text))
    ).catch(error => {
      console.error(`af_post_ajax: caught error from ${fname}`)
      bad();
    });
  });
}

// Repopulate form automagically
function af_repop_form(allofit) {
  for (let x in allofit) {
    if (x == "indexOf") continue;
    if (af_get_val(x) === undefined) af_get_val(`${x}[]`) !== undefined ? 
      af_set_val(`${x}[]`, allofit[x]) :
      af_new_el("input", {type: "hidden", name: x, value: allofit[x]}, document.forms[AF_FORM_NAME]);
    else af_set_val(x, allofit[x]);
  }
}

function af_submit_form(ajax_validation, up_to = "") {
  if (ajax_validation) {
    let Fieldlist = Object.keys(AF_FIELDS);
    for (let i in Fieldlist) {
      Fieldlist[i] += af_get_val(Fieldlist[i] + "[]") !== undefined ? "[]" : "";
      if (up_to == Fieldlist[i]) break;
    }
    let SubmitVals = af_get_val(Fieldlist);
    SubmitVals.af_action = af_ajax_action;
    SubmitVals.af_up_to = up_to;
    af_post_ajax(document[AF_FORM_NAME].action, SubmitVals).then(response => {
      if (response === "OK") {
        if (up_to) response = "[]";
        else {
          document[AF_FORM_NAME].submit();
          return;
        }
      }
      let json_data;
      try {
        json_data = JSON.parse(response);
      }
      catch (e) {
        json_data = {"other-errs": "Error in AJAX response - please reload the page"};
      }
      if (af_is_obj(json_data)) af_show_errors(json_data);
      else console.error("Invalid AJAX response");
    });
  }
  else document[AF_FORM_NAME].submit(); 
  if (!up_to) {
    af_el("af-submit-button").disabled = true;
    setTimeout(() => af_el("af-submit-button").disabled = false, 4000);
  }
}

function af_show_errors(Errors) {
  af_set_param("form af-error", "textContent", "");
  for (let i in Errors) {
    let inp_field = af_el(`af-${i}`);
    if (inp_field) {
      if (Errors[i] === "") {
        inp_field.classList.add("af-valid");
        inp_field.classList.remove("af-invalid");
      }
      else {
        inp_field.classList.remove("af-valid");
        inp_field.classList.add("af-invalid");
      }
    }
    if (af_el(`af-err-${i}`)) af_el(`af-err-${i}`).textContent = Errors[i];
  }
}

function af_init(ajax_validation, ajax_as_you_go) {
  af_el("af-submit-button").addEventListener("click", () => af_submit_form(ajax_validation));
  if (ajax_as_you_go) {
    for (let field of document[AF_FORM_NAME].elements) {
      let event_type = "";
      if (["radio", "select", "select-multiple", "checkbox"].includes(field.type)) event_type = "click";
      else if (!["hidden", "button"].includes(field.type)) event_type = "blur";
      if (event_type) field.addEventListener(event_type, () => af_submit_form(true, field.name.replace(/\[\]/, "")));
    }
  }
}
