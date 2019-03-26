<?php
/**
 * Created by PhpStorm.
 * User: schmunk
 * Date: 09.07.18
 * Time: 11:27
 */

namespace dmstr\widgets;


use dmstr\db\traits\ActiveRecordAccessTrait;
use kartik\select2\Select2;
use yii\base\Model;
use yii\base\Widget;
use Yii;
use yii\widgets\ActiveForm;

/**
 * @package dmstr\widgets
 *
 * @property ActiveForm $form
 * @property Model $model
 *
 * @property string $fieldOwner
 * @property string $fieldDomain
 * @property string $fieldRead
 * @property string $fieldUpdate
 * @property string $fieldDelete
 * @property string $fieldAppend
 */
class AccessInput extends Widget
{
    public $form;
    public $model;

    public $fieldOwner = 'access_owner';
    public $fieldDomain = 'access_domain';
    public $fieldRead = 'access_read';
    public $fieldUpdate = 'access_update';
    public $fieldDelete = 'access_delete';
    public $fieldAppend = 'access_append';

    /**
     * @return string
     */
    public function run()
    {
        $return = '';
        $userAuthItems = $this->model::getUsersAuthItems();
        $userDomains = $this->optsAccessDomain();
        $disabled = !$this->model->hasPermission($this->fieldUpdate);

        $return .= $this->form
            ->field($this->model, $this->fieldOwner)
            ->textInput(['readonly' => true]); // TODO: Check owner in model (has to be the same as current user)

        foreach (['domain', 'read', 'update', 'delete'] as $access) {
            $fieldName = 'field' . ucfirst($access);
            $return .= $this->form->field($this->model, $this->{$fieldName})->widget(
                Select2::class,
                [
                    'data' => $access === 'domain' ? $userDomains : $userAuthItems,
                    'options' => ['placeholder' => Yii::t('pages', 'Select ...')],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'disabled' => $disabled,
                    ],
                ]
            );

        }
        return $return;
    }


    /**
     * @return array Available domains for select
     */
    public function optsAccessDomain()
    {
        if (Yii::$app->user->can('access.availableDomains:any')) {
            $availableLanguages[ActiveRecordAccessTrait::$_all] = 'GLOBAL';
            foreach (\Yii::$app->urlManager->languages as $availablelanguage) {
                $lc_language = mb_strtolower($availablelanguage);
                $availableLanguages[$lc_language] = $lc_language;
            }
        } else {
            // allow current value
            $availableLanguages[$this->model->access_domain] = $this->model->access_domain;
            $availableLanguages[Yii::$app->language] = Yii::$app->language;
        }
        return $availableLanguages;
    }
}