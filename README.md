# AutoFrom

An easy form generator for PHP 8+

## Description

A system to easily create an interactive form with fields that meets WCAG 2.2 standards, and also
handles data validation.

## Usage

Install AutoForm.php in your project directory and require it. Instantiate the AutoForm object, then
after setting up your form fields, run the "execute" function.

```
<?php
  require "AutoForm.php";
  $AF = new AutoForm();

  // Set up your form fields here

  $AF->execute();
```

## Author

Mark Hall

