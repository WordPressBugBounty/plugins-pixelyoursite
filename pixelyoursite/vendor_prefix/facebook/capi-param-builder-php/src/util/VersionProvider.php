<?php
/*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace PYS_PRO_GLOBAL\FacebookAds;

class VersionProvider {
  /** PYS DEVIATION: memoised — see the vendoring script. */
  private static $version = null;

  public static function getVersion() {
    if (self::$version !== null) {
      return self::$version;
    }
    $composer_json_path = __DIR__ . '/../../composer.json';
    if (file_exists($composer_json_path)) {
      $composer_content = file_get_contents($composer_json_path);
      $composer_data = json_decode($composer_content, true);
      if (isset($composer_data['version'])) {
        return self::$version = $composer_data['version'];
      }
    }
    return self::$version = "";
  }
}
