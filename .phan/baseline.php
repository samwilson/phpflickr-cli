<?php
/**
 * This is an automatically generated baseline for Phan issues.
 * When Phan is invoked with --load-baseline=path/to/baseline.php,
 * The pre-existing issues listed in this file won't be emitted.
 *
 * This file can be updated by invoking Phan with --save-baseline=path/to/baseline.php
 * (can be combined with --load-baseline)
 */
return [
    'file_suppressions' => [
        'src/Command/ChecksumsCommand.php' => ['PhanTypeMismatchArgument'],
        'src/Command/DownloadCommandBase.php' => ['PhanTypeMismatchForeach'],
        'src/Template.php' => ['PhanDeprecatedFunction'],
    ],
];
