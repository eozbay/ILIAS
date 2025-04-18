<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\ContentPage\PageMetrics\Command\StorePageMetricsCommand;
use ILIAS\ContentPage\PageMetrics\PageMetricsService;
use ILIAS\ContentPage\PageMetrics\PageMetricsRepositoryImp;
use ILIAS\ContentPage\PageMetrics\Event\PageUpdatedEvent;

/**
 * Class ilObjContentPageGUI
 * @ilCtrl_isCalledBy ilObjContentPageGUI: ilRepositoryGUI
 * @ilCtrl_isCalledBy ilObjContentPageGUI: ilAdministrationGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilPermissionGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilInfoScreenGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilObjectCopyGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilExportGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilLearningProgressGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilContentPagePageGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilObjectCustomIconConfigurationGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilObjStyleSheetGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilObjectTranslationGUI
 * @ilCtrl_Calls ilObjContentPageGUI: ilPageMultiLangGUI
 */
class ilObjContentPageGUI extends ilObject2GUI implements ilContentPageObjectConstants, ilDesktopItemHandling
{
    /** @var \Psr\Http\Message\ServerRequestInterface */
    protected $request;
    /** @var ilCtrl */
    protected $ctrl;
    /** @var ilAccessHandler */
    protected $access;
    /** @var ilSetting */
    protected $settings;
    /** @var ilTabsGUI */
    protected $tabs;
    /** @var ilObjUser */
    protected $user;
    /** @var ilObjectService */
    protected $obj_service;
    /** @var ilNavigationHistory */
    protected $navHistory;
    /** @var ilErrorHandling */
    protected $error;
    /** @var \ILIAS\DI\Container */
    protected $dic;
    /** @var bool|string */
    protected $infoScreenEnabled = false;
    /** @var PageMetricsService */
    private $pageMetricsService;
    private \ILIAS\DI\UIServices $uiServices;

    /** @var ilHelp */
    protected $help;

    /**
     * @inheritdoc
     */
    public function __construct($a_id = 0, $a_id_type = self::REPOSITORY_NODE_ID, $a_parent_node_id = 0)
    {
        global $DIC;

        parent::__construct($a_id, $a_id_type, $a_parent_node_id);

        $this->dic = $DIC;
        $this->request = $this->dic->http()->request();
        $this->settings = $this->dic->settings();
        $this->access = $this->dic->access();
        $this->ctrl = $this->dic->ctrl();
        $this->tabs = $this->dic->tabs();
        $this->user = $this->dic->user();
        $this->obj_service = $this->dic->object();
        $this->navHistory = $this->dic['ilNavigationHistory'];
        $this->error = $this->dic['ilErr'];
        $this->help = $DIC['ilHelp'];
        $this->uiServices = $DIC->ui();

        $this->lng->loadLanguageModule('copa');
        $this->lng->loadLanguageModule('style');
        $this->lng->loadLanguageModule('content');

        if ($this->object instanceof ilObjContentPage) {
            $this->infoScreenEnabled = ilContainer::_lookupContainerSetting(
                $this->object->getId(),
                ilObjectServiceSettingsGUI::INFO_TAB_VISIBILITY,
                true
            );
        }

        $this->pageMetricsService = new PageMetricsService(
            new PageMetricsRepositoryImp($DIC->database()),
            $DIC->refinery()
        );
    }

    /**
     * @param string $target
     */
    public static function _goto(string $target) : void
    {
        global $DIC;

        $targetAttributes = explode('_', $target);
        $refId = (int) $targetAttributes[0];

        if ((int) $refId <= 0) {
            $DIC['ilErr']->raiseError($DIC->language()->txt('msg_no_perm_read'), $DIC['ilErr']->FATAL);
        }

        if ($DIC->access()->checkAccess('read', '', $refId)) {
            $DIC->ctrl()->setTargetScript('ilias.php');
            $DIC->ctrl()->initBaseClass('ilRepositoryGUI');
            if (isset($DIC->http()->request()->getQueryParams()['gotolp'])) {
                $DIC->ctrl()->setParameterByClass(__CLASS__, 'gotolp', 1);
            }
            $DIC->ctrl()->setParameterByClass(__CLASS__, 'ref_id', $refId);
            $DIC->ctrl()->redirectByClass([
                'ilRepositoryGUI',
                __CLASS__,
            ], self::UI_CMD_VIEW);
        } elseif ($DIC->access()->checkAccess("visible", "", $target)) {
            ilObjectGUI::_gotoRepositoryNode($target, "infoScreen");
        } elseif ($DIC->access()->checkAccess('read', '', ROOT_FOLDER_ID)) {
            ilUtil::sendInfo(sprintf(
                $DIC->language()->txt('msg_no_perm_read_item'),
                ilObject::_lookupTitle(ilObject::_lookupObjId($refId))
            ), true);

            $DIC->ctrl()->setTargetScript('ilias.php');
            $DIC->ctrl()->initBaseClass('ilRepositoryGUI');
            $DIC->ctrl()->setParameterByClass('ilRepositoryGUI', 'ref_id', ROOT_FOLDER_ID);
            $DIC->ctrl()->redirectByClass('ilRepositoryGUI');
        }

        $DIC['ilErr']->raiseError($DIC->language()->txt('msg_no_perm_read'), $DIC['ilErr']->FATAL);
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return self::OBJ_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function setTabs()
    {
        $this->help->setScreenIdComponent($this->object->getType());

        if ($this->checkPermissionBool('read')) {
            $this->tabs->addTab(
                self::UI_TAB_ID_CONTENT,
                $this->lng->txt('content'),
                $this->ctrl->getLinkTarget($this, self::UI_CMD_VIEW)
            );
        }

        if ($this->infoScreenEnabled && ($this->checkPermissionBool('visible') || $this->checkPermissionBool('read'))) {
            $this->tabs->addTab(
                self::UI_TAB_ID_INFO,
                $this->lng->txt('info_short'),
                $this->ctrl->getLinkTargetByClass('ilinfoscreengui', 'showSummary')
            );
        }

        if ($this->checkPermissionBool('write')) {
            $this->tabs->addTab(
                self::UI_TAB_ID_SETTINGS,
                $this->lng->txt('settings'),
                $this->ctrl->getLinkTarget($this, self::UI_CMD_EDIT)
            );
        }

        if (ilLearningProgressAccess::checkAccess($this->object->getRefId())) {
            $this->tabs->addTab(
                self::UI_TAB_ID_LP,
                $this->lng->txt('learning_progress'),
                $this->ctrl->getLinkTargetByClass('illearningprogressgui')
            );
        }

        if ($this->checkPermissionBool('write')) {
            $this->tabs->addTab(
                self::UI_TAB_ID_EXPORT,
                $this->lng->txt('export'),
                $this->ctrl->getLinkTargetByClass('ilexportgui')
            );
        }

        if ($this->checkPermissionBool('edit_permission')) {
            $this->tabs->addTab(
                self::UI_TAB_ID_PERMISSIONS,
                $this->lng->txt('perm_settings'),
                $this->ctrl->getLinkTargetByClass('ilpermissiongui', 'perm')
            );
        }
    }

    /**
     * @inheritdoc
     * @throws ilObjectException
     * @throws ilCtrlException
     * @throws ilException
     */
    public function executeCommand()
    {
        $nextClass = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd(self::UI_CMD_VIEW);

        $this->addToNavigationHistory();

        if (strtolower($nextClass) !== 'ilobjstylesheetgui') {
            $this->renderHeaderActions();
        }

        switch (strtolower($nextClass)) {
            case 'ilobjecttranslationgui':
                $this->checkPermission('write');

                $this->prepareOutput();
                $this->tabs->activateTab(self::UI_TAB_ID_SETTINGS);
                $this->setSettingsSubTabs(self::UI_TAB_ID_I18N);

                $transgui = new ilObjectTranslationGUI($this);
                $this->ctrl->forwardCommand($transgui);
                break;

            case 'ilobjstylesheetgui':
                $this->checkPermission('write');

                $this->setLocator();

                $this->ctrl->setReturn($this, 'editStyleProperties');
                $style_gui = new ilObjStyleSheetGUI(
                    '',
                    $this->object->getStyleSheetId(),
                    false,
                    false
                );
                $style_gui->omitLocator();
                if ($cmd === 'create' || $_GET['new_type'] === 'sty') {
                    $style_gui->setCreationMode(true);
                }

                if ($cmd === 'confirmedDelete') {
                    $this->object->setStyleSheetId(0);
                    $this->object->update();
                }

                $ret = $this->ctrl->forwardCommand($style_gui);

                if ($cmd === 'save' || $cmd === 'copyStyle' || $cmd === 'importStyle') {
                    $styleId = $ret;
                    $this->object->setStyleSheetId($styleId);
                    $this->object->update();
                    $this->ctrl->redirectByClass('ilobjstylesheetgui', 'edit');
                }
                break;

            case 'ilcontentpagepagegui':
                if (in_array(strtolower($cmd), array_map('strtolower', [
                    self::UI_CMD_COPAGE_DOWNLOAD_FILE,
                    self::UI_CMD_COPAGE_DISPLAY_FULLSCREEN,
                    self::UI_CMD_COPAGE_DOWNLOAD_PARAGRAPH,
                ]))
                ) {
                    if (!$this->checkPermissionBool('read')) {
                        $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
                    }
                } elseif (!$this->checkPermissionBool('write') || $this->user->isAnonymous()) {
                    $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
                }

                $this->prepareOutput();

                $this->tpl->setVariable(
                    'LOCATION_CONTENT_STYLESHEET',
                    ilObjStyleSheet::getContentStylePath($this->object->getStyleSheetId())
                );
                $this->tpl->setCurrentBlock('SyntaxStyle');
                $this->tpl->setVariable('LOCATION_SYNTAX_STYLESHEET', ilObjStyleSheet::getSyntaxStylePath());
                $this->tpl->parseCurrentBlock();

                $forwarder = new ilContentPagePageCommandForwarder(
                    $this->request,
                    $this->ctrl,
                    $this->tabs,
                    $this->lng,
                    $this->object,
                    $this->user
                );

                $forwarder->addUpdateListener(function (PageUpdatedEvent $event) : void {
                    $this->pageMetricsService->store(
                        new StorePageMetricsCommand(
                            (int) $this->object->getId(),
                            $event->page()->getLanguage()
                        )
                    );
                });

                $pageContent = $forwarder->forward();
                if (strlen($pageContent) > 0) {
                    $this->tpl->setContent($pageContent);
                }
                break;

            case 'ilinfoscreengui':
                if (!$this->infoScreenEnabled) {
                    return null;
                }
                $this->prepareOutput();

                $this->infoScreenForward();
                break;

            case 'ilcommonactiondispatchergui':
                $this->ctrl->forwardCommand(ilCommonActionDispatcherGUI::getInstanceFromAjaxCall());
                break;

            case 'ilpermissiongui':
                $this->checkPermission('edit_permission');

                $this->prepareOutput();
                $this->tabs->activateTab(self::UI_TAB_ID_PERMISSIONS);

                $this->ctrl->forwardCommand(new ilPermissionGUI($this));
                break;

            case 'illearningprogressgui':
                if (!ilLearningProgressAccess::checkAccess($this->object->getRefId())) {
                    $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
                }

                $this->prepareOutput();
                $this->tabs->activateTab(self::UI_TAB_ID_LP);

                $this->ctrl->forwardCommand(new ilLearningProgressGUI(
                    ilLearningProgressGUI::LP_CONTEXT_REPOSITORY,
                    $this->object->getRefId(),
                    isset(
                        $this->request->getQueryParams()['user_id']) &&
                        is_numeric(
                            $this->request->getQueryParams()['user_id']
                        ) ?
                        (int) $this->request->getQueryParams()['user_id'] :
                        $this->user->getId()
                ));
                break;

            case 'ilexportgui':
                $this->checkPermission('write');

                $this->prepareOutput();
                $this->tabs->activateTab(self::UI_TAB_ID_EXPORT);

                $gui = new ilExportGUI($this);
                $gui->addFormat('xml');
                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilobjectcustomiconconfigurationgui':
                if (!$this->checkPermissionBool('write') || !$this->settings->get('custom_icons')) {
                    $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
                }

                $this->prepareOutput();
                $this->tabs->activateTab(self::UI_TAB_ID_SETTINGS);
                $this->setSettingsSubTabs(self::UI_TAB_ID_ICON);

                require_once 'Services/Object/Icon/classes/class.ilObjectCustomIconConfigurationGUI.php';
                $gui = new ilObjectCustomIconConfigurationGUI($this->dic, $this, $this->object);
                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilobjectcopygui':
                $this->tpl->loadStandardTemplate();

                $gui = new ilObjectCopyGUI($this);
                $gui->setType(self::OBJ_TYPE);

                $this->ctrl->forwardCommand($gui);
                break;

            default:
                switch (true) {
                    case in_array(strtolower($cmd), array_map('strtolower', [self::UI_CMD_EDIT, self::UI_CMD_UPDATE])):
                        $this->setSettingsSubTabs(self::UI_TAB_ID_SETTINGS);
                        break;
                }

                if (in_array(strtolower($cmd), array_map('strtolower', ['addToDesk', 'removeFromDesk']))) {
                    $this->ctrl->setCmd($cmd . 'Object');
                }

                return parent::executeCommand();
        }
        return null;
    }

    public function addToNavigationHistory() : void
    {
        if (!$this->getCreationMode()) {
            if ($this->checkPermissionBool('read')) {
                $this->navHistory->addItem(
                    $this->object->getRefId(),
                    ilLink::_getLink($this->object->getRefId(), $this->object->getType()),
                    $this->object->getType()
                );
            }
        }
    }

    public function renderHeaderActions() : void
    {
        if (!$this->getCreationMode()) {
            if ($this->checkPermissionBool('read')) {
                $this->addHeaderAction();
            }
        }
    }

    /**
     * @throws ilCtrlException
     */
    public function infoScreenForward() : void
    {
        if (!$this->infoScreenEnabled) {
            return;
        }

        if (!$this->checkPermissionBool('visible') && !$this->checkPermissionBool('read')) {
            $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
        }

        $this->tabs->activateTab(self::UI_TAB_ID_INFO);

        $info = new ilInfoScreenGUI($this);
        $info->enableLearningProgress(true);
        $info->enablePrivateNotes();
        $info->addMetaDataSections($this->object->getId(), 0, $this->object->getType());

        $this->ctrl->forwardCommand($info);
    }

    /**
     * Sub tab configuration of the settings area
     * @param string $activeTab
     */
    protected function setSettingsSubTabs(string $activeTab) : void
    {
        if ($this->checkPermissionBool('write')) {
            $this->tabs->addSubTab(
                self::UI_TAB_ID_SETTINGS,
                $this->lng->txt('settings'),
                $this->ctrl->getLinkTarget($this, self::UI_CMD_EDIT)
            );

            if ($this->settings->get('custom_icons')) {
                $this->tabs_gui->addSubTab(
                    self::UI_TAB_ID_ICON,
                    $this->lng->txt('icon_settings'),
                    $this->ctrl->getLinkTargetByClass('ilObjectCustomIconConfigurationGUI')
                );
            }

            $this->tabs_gui->addSubTab(
                self::UI_TAB_ID_STYLE,
                $this->lng->txt('cont_style'),
                $this->ctrl->getLinkTarget($this, 'editStyleProperties')
            );

            $this->tabs_gui->addSubTab(
                self::UI_TAB_ID_I18N,
                $this->lng->txt('obj_multilinguality'),
                $this->ctrl->getLinkTargetByClass(ilObjectTranslationGUI::class)
            );

            $this->tabs->activateSubTab($activeTab);
        }
    }

    /**
     * @inheritdoc
     */
    public function addLocatorItems()
    {
        if ($this->object instanceof ilObject) {
            $this->locator->addItem(
                $this->object->getTitle(),
                $this->ctrl->getLinkTarget($this, self::UI_CMD_VIEW),
                '',
                $this->object->getRefId()
            );
        }
    }

    /**
     *
     * @throws ilCtrlException
     */
    public function infoScreen() : void
    {
        $this->ctrl->setCmd('showSummary');
        $this->ctrl->setCmdClass('ilinfoscreengui');

        $this->infoScreenForward();
    }


    /**
     * Shows the content of the object
     * @throws ilException
     * @throws ilObjectException
     */
    public function view()
    {
        $this->checkPermission('read');

        $this->populateContentToolbar();

        $this->tabs->activateTab(self::UI_TAB_ID_CONTENT);

        $this->tpl->setPermanentLink($this->object->getType(), $this->object->getRefId(), '', '_top');

        $this->tpl->setContent($this->getContent());
    }

    protected function populateContentToolbar() : void
    {
        if (!$this->user->isAnonymous() && $this->checkPermissionBool('write')) {
            $this->lng->loadLanguageModule('cntr');
            $this->toolbar->addComponent(
                $this->uiServices->factory()->button()->primary(
                    $this->lng->txt('cntr_text_media_editor'),
                    $this->ctrl->getLinkTargetByClass(ilContentPagePageGUI::class, 'edit')
                )
            );
        }
    }

    /**
     * @param string $ctrlLink A link which describes the target controller for all page object links/actions
     * @return string
     * @throws ilException
     */
    public function getContent(string $ctrlLink = '') : string
    {
        if ($this->checkPermissionBool('read')) {
            $this->object->trackProgress((int) $this->user->getId());

            $this->initStyleSheets();

            $forwarder = new ilContentPagePageCommandForwarder(
                $this->request,
                $this->ctrl,
                $this->tabs,
                $this->lng,
                $this->object,
                $this->user
            );
            $forwarder->setPresentationMode(ilContentPagePageCommandForwarder::PRESENTATION_MODE_PRESENTATION);

            return $forwarder->forward($ctrlLink);
        }

        return '';
    }

    protected function initStyleSheets() : void
    {
        $this->tpl->setVariable(
            'LOCATION_CONTENT_STYLESHEET',
            ilObjStyleSheet::getContentStylePath($this->object->getStyleSheetId())
        );
        $this->tpl->setCurrentBlock('SyntaxStyle');
        $this->tpl->setVariable('LOCATION_SYNTAX_STYLESHEET', ilObjStyleSheet::getSyntaxStylePath());
        $this->tpl->parseCurrentBlock();
    }

    /**
     * @inheritdoc
     */
    protected function afterSave(ilObject $a_new_object)
    {
        $a_new_object->getObjectTranslation()->addLanguage(
            $this->lng->getDefaultLanguage(),
            $a_new_object->getTitle(),
            $a_new_object->getDescription(),
            true,
            true
        );
        $a_new_object->getObjectTranslation()->save();

        ilUtil::sendSuccess($this->lng->txt('object_added'), true);
        $this->ctrl->redirect($this, 'edit');
    }

    /**
     * @inheritdoc
     */
    protected function setTitleAndDescription()
    {
        parent::setTitleAndDescription();

        $icon = ilObject::_getIcon($this->object->getId(), 'big', $this->object->getType());
        $this->tpl->setTitleIcon($icon, $this->lng->txt('obj_' . $this->object->getType()));
    }

    /**
     * @inheritdoc
     */
    protected function initEditCustomForm(ilPropertyFormGUI $a_form)
    {
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($this->lng->txt('obj_features'));
        $a_form->addItem($sh);

        ilObjectServiceSettingsGUI::initServiceSettingsForm(
            $this->object->getId(),
            $a_form,
            [
                ilObjectServiceSettingsGUI::INFO_TAB_VISIBILITY
            ]
        );

        $presentationHeader = new ilFormSectionHeaderGUI();
        $presentationHeader->setTitle($this->lng->txt('settings_presentation_header'));
        $a_form->addItem($presentationHeader);
        $this->obj_service->commonSettings()->legacyForm($a_form, $this->object)->addTileImage();
    }

    /**
     * @inheritdoc
     */
    protected function getEditFormCustomValues(array &$a_values)
    {
        $a_values[ilObjectServiceSettingsGUI::INFO_TAB_VISIBILITY] = $this->infoScreenEnabled;
    }

    /**
     * @inheritdoc
     */
    protected function updateCustom(ilPropertyFormGUI $a_form)
    {
        ilObjectServiceSettingsGUI::updateServiceSettingsForm(
            $this->object->getId(),
            $a_form,
            [
                ilObjectServiceSettingsGUI::INFO_TAB_VISIBILITY
            ]
        );
        $this->obj_service->commonSettings()->legacyForm($a_form, $this->object)->saveTileImage();
    }

    /**
     * @throws ilObjectException
     */
    protected function editStyleProperties() : void
    {
        $this->checkPermission('write');

        $this->tabs->activateTab(self::UI_TAB_ID_SETTINGS);
        $this->setSettingsSubTabs(self::UI_TAB_ID_STYLE);

        $form = $this->buildStylePropertiesForm();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * @return ilPropertyFormGUI
     */
    protected function buildStylePropertiesForm() : ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        $fixedStyle = $this->settings->get('fixed_content_style_id');
        $defaultStyle = $this->settings->get('default_content_style_id');
        $styleId = $this->object->getStyleSheetId();

        if ($fixedStyle > 0) {
            $st = new ilNonEditableValueGUI($this->lng->txt("cont_current_style"));
            $st->setValue(
                ilObject::_lookupTitle($fixedStyle) . " (" . $this->lng->txt("global_fixed") . ")"
            );
            $form->addItem($st);
        } else {
            $st_styles = ilObjStyleSheet::_getStandardStyles(
                true,
                false,
                (int) $_GET["ref_id"]
            );

            if ($defaultStyle > 0) {
                $st_styles[0] = ilObject::_lookupTitle($defaultStyle) . ' (' . $this->lng->txt('default') . ')';
            } else {
                $st_styles[0] = $this->lng->txt('default');
            }
            ksort($st_styles);

            if ($styleId > 0) {
                if (!ilObjStyleSheet::_lookupStandard($styleId)) {
                    $st = new ilNonEditableValueGUI($this->lng->txt('cont_current_style'));
                    $st->setValue(ilObject::_lookupTitle($styleId));
                    $form->addItem($st);

                    $form->addCommandButton('editStyle', $this->lng->txt('cont_edit_style'));
                    $form->addCommandButton('deleteStyle', $this->lng->txt('cont_delete_style'));
                }
            }

            if ($styleId <= 0 || ilObjStyleSheet::_lookupStandard($styleId)) {
                $style_sel = new ilSelectInputGUI($this->lng->txt('cont_current_style'), 'style_id');
                $style_sel->setOptions($st_styles);
                $style_sel->setValue($styleId);
                $form->addItem($style_sel);
                $form->addCommandButton('saveStyleSettings', $this->lng->txt('save'));
                $form->addCommandButton('createStyle', $this->lng->txt('sty_create_ind_style'));
            }
        }

        $form->setTitle($this->lng->txt("cont_style"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }

    protected function createStyle() : void
    {
        $this->ctrl->redirectByClass('ilobjstylesheetgui', 'create');
    }

    protected function editStyle() : void
    {
        $this->ctrl->redirectByClass('ilobjstylesheetgui', 'edit');
    }

    protected function deleteStyle() : void
    {
        $this->ctrl->redirectByClass('ilobjstylesheetgui', 'delete');
    }

    /**
     * @throws ilObjectException
     */
    protected function saveStyleSettings() : void
    {
        $this->checkPermission('write');

        if (
            $this->settings->get('fixed_content_style_id') <= 0 &&
            (
                ilObjStyleSheet::_lookupStandard(
                    $this->object->getStyleSheetId()
                ) ||
                $this->object->getStyleSheetId() == 0
            )
        ) {
            $this->object->setStyleSheetId(ilUtil::stripSlashes($_POST['style_id']));
            $this->object->update();
            ilUtil::sendSuccess($this->lng->txt('msg_obj_modified'), true);
        }

        $this->ctrl->redirect($this, 'editStyleProperties');
    }
}
