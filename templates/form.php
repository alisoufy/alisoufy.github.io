<?php

require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . "/wp-load.php";

$formId = isset($_GET['formId']) ? $_GET['formId'] : '';
$pageId = isset($_GET['id']) ? $_GET['id'] : '';
if (!$formId || !$pageId) {
    echo json_encode(array('error' => 'Form id or page id not found'));
    exit;
}

$formsData = null;

if ($pageId == 'header' || $pageId == 'footer') {
    $headerNp = get_option('headerNp', true);
    $footerNp = get_option('footerNp', true);
    $item = null;
    if ($pageId == 'header') {
        $item = $headerNp;
    } else if ($pageId == 'footer') {
        $item = $footerNp;
    }
    if ($item) {
        $item = json_decode($item, true);
        $formsData = isset($item['formsData']) ? json_decode($item['formsData'], true) : array();
    }
} else {
    $data_provider = np_data_provider($pageId);
    $formsData = $data_provider->getFormsData() ? json_decode($data_provider->getFormsData(), true) : array();
}

if ($formsData) {
    $foundForm = null;
    for ($i = 0; $i < count($formsData); $i++) {
        $form = $formsData[$i];
        $str = json_encode($form);
        if (strpos($str, 'form-' . $formId) !== false) {
            $foundForm = $form;
            break;
        }
    }
    if ($foundForm) {
        $convertedForm = array(
            'subject' => $foundForm['subject'],
            'email_message' => $foundForm['emailMsg'],
            'success_redirect' => '',
            'email' => array(
                'from' => $foundForm['emailfrom'],
                'to' => $foundForm['emailto']
            ),
            'fields' => array(),
        );
        for ($j = 0; $j < count($foundForm['fields']); $j++) {
            $field = $foundForm['fields'][$j];
            $convertedForm['fields'][$field['name']] = array(
                'order' => $field['order'],
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'],
                'errors' => array(
                    'required' => 'Field \'' . $field['label'] . '\' is required.'
                )
            );
        }

        $formsDir = dirname(dirname(__FILE__)) . '/forms/FormProcessor.php';
        if (file_exists($formsDir)) {
            include_once $formsDir;
            $processor = new FormProcessor();
            $processor->process($convertedForm);
            exit;
        }
    }
}
?>