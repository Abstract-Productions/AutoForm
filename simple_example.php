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
    print "<p>Thank you for filling in the form, {$AF->Sanitized['first_name']}!</p>\n";
    $AF->foot();
  }
