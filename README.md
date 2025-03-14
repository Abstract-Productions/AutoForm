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

  // Form fields go here

  $AF->execute();
```

The next step is to create the fields. Here is a simple example of three fields:

```
<?php
    require "AutoForm.php";
    $AF = new AutoForm();

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
            "validate" => [
                "regex" => [
                    "" => "Please enter your email address",
                    "/.+@.+\..+/i" => false
                ],
                "default" => "Invalid email address"
            ]
        ]
    ]);
```

## Author

Mark Hall

