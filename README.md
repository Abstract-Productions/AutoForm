# AutoFrom

An easy form generator for PHP 8+. No bulky frameworks or dependencies, just one small PHP include and
one small JavaScript file.

## Description

A system to easily create an interactive form with fields that meets WCAG accessibility standards, and
also handles data validation and robot detection.

## Usage

Install AutoForm.php in your project directory and require it. Instantiate the AutoForm object, then
after setting up your form fields, use the "execute" function, which will return TRUE only when all
fields are filled out and validation is passed.

The HTML around the form can be set via "header" and "footer" variables in the object, which can be
passed in an array when instantiating the object.

In our header, we'll include the AutoForm.js script, which is necessary. The package also comes in with
a premade AutoForm.css which sets some default styles to the form elements. You can edit this file or
create your own with your own customizations.

```
<?php
  require "AutoForm.php";
  $AF = new AutoForm([
    "header" => <<<EOF
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <script src="AutoForm.js"></script>
    <link rel="stylesheet" href="AutoForm.css">
    <title>Sample Form</title>
  </head>
  <body>
    <h1>Sample Form</h1>

EOF,
    "footer" => <<<EOF
  </body>
</html>
EOF
  ]);

  // Form fields will go here

  if ($AF->execute()) {
    // Code here will be run after validation is complete
  }
```

The next step is to create the fields, which you can do in one statement using the "add_fields" function
with an associative array of arrays. The keys of the array serve as the field names, and in the
following example, we set the visible label and the validation message for missing fields in an array
for each field.

In the block after the "execute" function, we show a simple message, using the "Santized" array, which
holds an escaped copy of the POST variables.


```
<?php
  require "AutoForm.php";
  $AF = new AutoForm([
    "header" => <<<EOF
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <script src="AutoForm.js"></script>
    <link rel="stylesheet" href="AutoForm.css">
    <title>Sample Form</title>
  </head>
  <body>
    <h1>Sample Form</h1>

EOF,
    "footer" => <<<EOF
  </body>
</html>
EOF
  ]);

  $AF->add_fields([
    "first_name" => [
      "label" => "First Name",
      "validate" => "Please enter your first name"
    ],
    "last_name" => [
      "label" => "Last Name",
      "validate" => "Please enter your last name"
    ],
    "email" => [
      "label" => "Email Address",
      "validate" => "Please enter your email address"
    ]
  ]);

  if ($AF->execute()) {
    $AF->head();
    print "<p>Thank you for filling in the form, ".$AF->Sanitized['first_name']."!</p>\n";
    $AF->foot();
  }
```

## Author

Mark Hall

