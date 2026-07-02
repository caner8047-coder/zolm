<?php
$html = '<script>window.__SEARCH_APP_INITIAL_STATE__ = {"foo":"bar"};</script>';
preg_match('/window\.__SEARCH_APP_INITIAL_STATE__\s*=\s*(\{.+?\});/is', $html, $matches);
var_dump($matches);
