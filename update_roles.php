<?php
$files = [
    'e:\attendence-app\api\controllers\TeamController.php',
    'e:\attendence-app\api\controllers\FileProxyController.php',
    'e:\attendence-app\api\controllers\EmployeeDocumentController.php',
    'e:\attendence-app\api\controllers\AdminEmployeeController.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace 'company' with 'company_admin' ONLY inside requireAuth calls
    // But honestly replacing 'company' with 'company_admin' anywhere in these specific files is safe for these roles.
    $content = str_replace("['company', 'super_admin']", "['company_admin', 'super_admin']", $content);
    $content = str_replace("['company', 'super_admin', 'employee']", "['company_admin', 'super_admin', 'employee']", $content);
    $content = str_replace("['employee', 'company', 'super_admin']", "['employee', 'company_admin', 'super_admin']", $content);
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
