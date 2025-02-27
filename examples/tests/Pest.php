<?php

pest()->beforeEach(function () {
    expect(class_exists('LibSQL'))->toBe(true);
})->afterEach(function () {
    if (file_exists("database.db")) {
        unlink("database.db");
    }

    if (file_exists('memory')) {
        unlink('memory');
    }
});