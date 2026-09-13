<?php

namespace Module\Utils;

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use CAdminTabControl;

class Options
{
    protected string $moduleId;
    protected int $groupRightsVersion;
    protected array $tabs = [];
    protected array $options = [];
    protected bool $hasRightsTab = false;
    protected string $rightsTabDiv = '';

    public function __construct(string $moduleId, int $groupRightsVersion = 1)
    {
        $this->moduleId = $moduleId;
        $this->groupRightsVersion = $groupRightsVersion;
    }

    public function addTab(string $div, string $tab, string $title, array $options = []): self
    {
        $this->tabs[] = [
            'DIV' => $div,
            'TAB' => $tab,
            'TITLE' => $title,
        ];

        if (!empty($options)) {
            $this->options[$div] = $options;
        }

        return $this;
    }

    public function addRightsTab(string $div = 'rights', string $tab = 'Настройка прав', string $title = 'Настройка прав и доступов'): self
    {
        $this->hasRightsTab = true;
        $this->rightsTabDiv = $div;

        $this->tabs[] = [
            'DIV' => $div,
            'TAB' => $tab,
            'TITLE' => $title,
        ];

        return $this;
    }

    public function process(): void
    {
        global $APPLICATION, $USER, $adminPage, $adminMenu, $adminChain;

        $moduleRight = $APPLICATION->GetGroupRight($this->moduleId);
        if ($moduleRight < 'R') {
            $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
            return;
        }

        $request = Application::getInstance()->getContext()->getRequest();
        $isPost = $request->isPost() && $request->getPost('Update') && check_bitrix_sessid();
        $module_id = $mid = $this->moduleId;

        $tabControl = new CAdminTabControl('tabControl', $this->tabs);

        if ($isPost && $moduleRight >= 'W') {
            foreach ($this->tabs as $tab) {
                if (isset($this->options[$tab['DIV']])) {
                    __AdmSettingsSaveOptions($this->moduleId, $this->options[$tab['DIV']]);
                }
            }

            if ($this->hasRightsTab) {
                // Инициализация переменных, которые жестко требует ядро Битрикса для сохранения прав
                $REQUEST_METHOD = $request->getRequestMethod();
                $Update = $request->getPost('Update');
                $GROUPS = $request->getPost('GROUPS') ?? [];
                $RIGHTS = $request->getPost('RIGHTS') ?? [];
                $SITES = $request->getPost('SITES') ?? [];

                ob_start();
                if ($this->groupRightsVersion === 2) {
                    require Application::getDocumentRoot() . '/bitrix/modules/main/admin/group_rights2.php';
                } else {
                    require Application::getDocumentRoot() . '/bitrix/modules/main/admin/group_rights.php';
                }
                ob_end_clean();
            }

            LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($this->moduleId) . '&lang=' . LANGUAGE_ID . '&' . $tabControl->ActiveTabParam());
        }

        require_once Application::getDocumentRoot() . '/bitrix/modules/main/include/prolog_admin_after.php';

        $tabControl->Begin();
        ?>
        <form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($this->moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
            <?php bitrix_sessid_post(); ?>

            <?php foreach ($this->tabs as $tab): ?>
                <?php $tabControl->BeginNextTab(); ?>

                <?php if ($this->hasRightsTab && $tab['DIV'] === $this->rightsTabDiv): ?>
                    <?php
                    $module_id = $this->moduleId;
                    if ($this->groupRightsVersion === 2) {
                        require Application::getDocumentRoot() . '/bitrix/modules/main/admin/group_rights2.php';
                    } else {
                        require Application::getDocumentRoot() . '/bitrix/modules/main/admin/group_rights.php';
                    }
                    ?>
                <?php elseif (isset($this->options[$tab['DIV']])): ?>
                    <?php __AdmSettingsDrawList($this->moduleId, $this->options[$tab['DIV']]); ?>
                <?php endif; ?>

            <?php endforeach; ?>

            <?php $tabControl->Buttons(); ?>
            <input type="submit" name="Update" value="<?= Loc::getMessage('MAIN_SAVE') ?>" class="adm-btn-save" <?= ($moduleRight < 'W' ? 'disabled' : '') ?>>
        </form>
        <?php
        $tabControl->End();
    }
}