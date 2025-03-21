// Autoform v1.0
// By Mark Hall
// https://abstract-productions.net
"use strict";

// Default form name is form1 but can change it after including autoform.js
let AF_FORM_NAME = "form1", AF_select_label = "", AF_field_list = [];

for (let lmnt of ["af-field", "af-value", "af-error"])
  customElements.define(lmnt, class extends HTMLElement {});

// Shortcut to set options in a DOM element and/or append it to another element
function set_elops(elem, elops, appendto = "", children = []) {
  if (is_el(elops)) [elops, appendto] = [{}, elops];
  if (is_arr(elops)) [elops, children] = [{}, elops];
  if (is_arr(appendto)) [appendto, children] = [is_arr(children) ? "" : children, appendto];
  for (let i in elops) typeof elem[i] === "function" ?
    Object.keys(elops[i]).map(j => elem[i](j, elops[i][j])) :
    i.match(/\./) ? elem[i.split(/\./)[0]][i.split(/\./)[1]] = elops[i] : elem[i] = elops[i];
  for (let i of is_arr(children) ? children : [children]) elem.appendChild(i);
  return appendto ? get_elem(appendto).appendChild(elem) : elem;
}

function set_param(id, param, val) {
  let results = {};
  if (is_obj(id))
    for (let i in id) results[i] = set_param(i, id[i]);
  else if (is_obj(param))
    for (let i in param) results[i] = set_param(id, i, param[i]);
  else {
    let elm;
    if (!is_el(id) && id.match(/[\.#>\[\*\+: \(,~\|]/)) {
      results = val;
      for (let e of qs(id)) set_elops(e, {[param]: val});
    }
    else {
      elm = el(id) || document.forms[AF_FORM_NAME][id];
      if (elm === undefined) return console.error(`set_param: no element found for ID ${id}`);
      set_elops(elm, {[param]: val});
      return val;
    }
  }
  return results;
}

function set_style(id, param, val) {
  let results = {};
  if (is_obj(id)) Object.keys(id).forEach(i => results[i] = set_style(i, id[i]));
  else if (is_obj(param)) Object.keys(param).forEach(i => results[i] = set_style(id, i, param[i]));
  else return set_param(id, `style.${param}`, val);
  return results;
}

function set_plaintext(id, val = "") {
  if (is_obj(id)) {
    let results = {};
    for (let i in id) results[i] = set_plaintext(i, id[i], html);
    return results;
  }
  return set_param(id, "textContent", (typeof val === "string" ? val : val.toString()).replace(/\\/g, "\\\\"));
}

function el() {
  let returnme = [];
  for (let i of arguments) returnme.push(is_el(i) ? i : document.getElementById(i));
  return returnme.length > 1 ? returnme : returnme[0];
}

function get_elem() {
  let returnme = [];
  for (let i of arguments) 
    returnme.push(is_el(i) ? i : i.charAt(0) == "#" ? el(i.substr(1)) : el(i) ? el(i) : document.forms[AF_FORM_NAME] ? document.forms[AF_FORM_NAME][i] || null : null);
  return returnme.length > 1 ? returnme : returnme[0];
}

var qs = i => i instanceof NodeList ? i : document.querySelectorAll(i),
is_obj = i => typeof i === "object" && i !== null && !is_el(i),
is_arr = i => i instanceof Array,
is_el = i => i instanceof Element,
build_query = params => is_obj(params) ? Object.keys(params).map(key => is_arr(params[key]) ? params[key].map(val => encodeURIComponent(key.replace("[]", "") + "[]") + "=" + encodeURIComponent(val)).join("&") : encodeURIComponent(key) + "=" + encodeURIComponent(params[key])).join("&") : params,
swap_visibility = (chkval, ops) => Object.keys(ops).map(x => el(ops[x]).style.display = get_val(chkval) == x ? "" : "none"),
new_el = (elname, elops = {}, appendto = "", children = []) => set_elops(document.createElement(elname), elops, appendto, children);

function text_node(text, appendto = "") {
  let n = document.createTextNode(text);
  return appendto == "" ? n : appendto.appendChild(n);
}

// Get value from whatever kind of field it's in
function get_val(fname) {
  if (is_arr(fname)) {
    let results = {};
    fname.forEach(fn => results[fn] = get_val(fn));
    return results;
  }
  if (is_obj(fname)) {
    for (let fn in fname) fname[fn] = get_val(fn);
    return fname;
  }

  let field_obj = get_elem(fname);
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

  // Special case for hidden field as default value to checkbox, or regular field preceded by hidden field
  if (field_obj.length == 2 && field_obj[0].type == "hidden" && field_obj[1].type != "hidden")
    return !["checkbox", "radio"].includes(field_obj[1].type) || field_obj[1].checked ? field_obj[field_obj.length - 1].value : field_obj[0].value;
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
    if (field_obj.value === undefined) console.error(`set_val: no value atribute for ${fname}`);
    return field_obj.value;
  }
  return "";
}

// Set value in whatever kind of field it's in
function set_val(fname, new_val) {
  if (is_obj(fname)) {
    let results = {};
    for (let x in fname) {
      results[x] = set_val(x, fname[x]);
      if (results[x] === undefined) delete results[x];
    }
    return results;
  }
  let field_obj = get_elem(fname);
  if (field_obj === null) return console.error(`set_val: ID or field ${fname} undefined`);
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
function post_ajax(fname, post_vars = [], meth = "POST") {
  return new Promise((ok, bad) => {
    fetch(fname, meth == "POST" ? {
      method: "POST",
      body: build_query(post_vars),
      headers: {"Content-Type": "application/x-www-form-urlencoded"}
    } : {
      method: "GET"
    }).then(response => response.status != 200 ? bad(console.error(`post_ajax: no response from ${fname}`))
      : response.text().then(text => ok(text))
    ).catch(error => {
      console.error(`post_ajax: caught error from ${fname}`)
      bad();
    });
  });
}

// Repopulate form automagically
function repop_form(allofit) {
  for (let x in allofit) {
    if (x == "indexOf") continue;
    if (get_val(x) === undefined) get_val(`${x}[]`) !== undefined ? 
      set_val(`${x}[]`, allofit[x]) :
      new_el("input", {type: "hidden", name: x, value: allofit[x]}, document.forms[AF_FORM_NAME]);
    else set_val(x, allofit[x]);
  }
}

function af_submit_form(ajax_validation, up_to = "") {
  if (ajax_validation) {
    let Fieldlist = Object.keys(AF_FIELDS);
    for (let i in Fieldlist) {
      Fieldlist[i] += get_val(Fieldlist[i] + "[]") !== undefined ? "[]" : "";
    }
    let SubmitVals = get_val(Fieldlist);
    SubmitVals.af_action = "ajax";
    post_ajax(document[AF_FORM_NAME].action, SubmitVals).then(response => {
      if (response === "OK") {
        document[AF_FORM_NAME].submit();
        return;
      }
      let json_data = JSON.parse(response);
      if (is_obj(json_data)) af_show_errors(json_data);
      else console.error("Invalid AJAX response");
    });
  }
  else document[AF_FORM_NAME].submit(); 
  el("af-submit-button").disabled = true;
  setTimeout(() => el("af-submit-button").disabled = false, 4000);
}

function af_show_errors(Errors) {
  set_param("form af-error", "textContent", "");
  for (let i in Errors) {
    el(`af-err-${i}`).textContent = Errors[i];
  }
}
