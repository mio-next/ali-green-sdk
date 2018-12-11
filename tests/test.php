<?php

require __DIR__ . '/../vendor/autoload.php';

use NetCasts\AliGreen\Green;

$green = new Green('ak', 'sk');

var_dump(
    $green->image("http://thetheme.io/assets/img/thedocs.jpg"),
    $green->text("你奶奶的")
);