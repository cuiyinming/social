<?php

Route::get('/', 'HomeController@index');
Route::get('/{short_code}', 'HomeController@short');
Route::get('/short/gain', 'HomeController@shortGain');
