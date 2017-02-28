<?php

/**
 *
 * @package FoxyStripe
 *
 */
class ProductPage extends Page implements PermissionProvider
{

    private static $allowed_children = 'none';
    private static $default_parent = 'ProductHolder';
    private static $can_be_root = false;

    /**
     * @var array
     */
    private static $db = array(
        'Price' => 'Currency',
        'Weight' => 'Decimal',
        'Code' => 'Varchar(100)',
        'ReceiptTitle' => 'HTMLVarchar(255)',
        'Featured' => 'Boolean',
        'Available' => 'Boolean',
    );

    /**
     * @var array
     */
    private static $has_one = array(
        'PreviewImage' => 'Image',
        'Category' => 'ProductCategory'
    );

    private static $has_many = array(
        'ProductImages' => 'ProductImage',
        'ProductOptions' => 'OptionItem',
        'OrderDetails' => 'OrderDetail',
    );

    private static $belongs_many_many = array(
        'ProductHolders' => 'ProductHolder'
    );

    private static $singular_name = 'Product';
    private static $plural_name = 'Products';
    private static $description = 'A product that can be added to the shopping cart';

    private static $indexes = array(
        'Code' => true // make unique
    );

    private static $defaults = array(
        'ShowInMenus' => false,
        'Available' => true,
        'Weight' => '1.0'
    );

    private static $summary_fields = array(
        'Title',
        'Code',
        'Price.Nice',
        'Category.Title'
    );

    private static $searchable_fields = array(
        'Title',
        'Code',
        'Featured',
        'Available',
        'Category.ID'
    );

    function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels();

        $labels['Title'] = _t('ProductPage.TitleLabel', 'Name');
        $labels['Code'] = _t('ProductPage.CodeLabel', "Code");
        $labels['Price.Nice'] = _t('ProductPage.PriceLabel', 'Price');
        $labels['Featured.Nice'] = _t('ProductPage.NiceLabel', 'Featured');
        $labels['Available.Nice'] = _t('ProductPage.AvailableLabel', 'Available');
        $labels['Category.ID'] = _t('ProductPage.IDLabel', 'Category');
        $labels['Category.Title'] = _t('ProductPage.CategoryTitleLabel', 'Category');

        return $labels;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // allow extensions of ProductPage to override the PreviewImage field description
        $previewDescription = ($this->stat('customPreviewDescription')) ? $this->stat('customPreviewDescription') : _t('ProductPage.PreviewImageDescription',
            'Image used throughout site to represent this product');

        // Cateogry Dropdown field w/ add new
        $source = function () {
            return ProductCategory::get()->map()->toArray();
        };
        $catField = DropdownField::create('CategoryID', _t('ProductPage.Category', 'FoxyCart Category'), $source())
            ->setEmptyString('')
            ->setDescription(_t(
                'ProductPage.CategoryDescription',
                'Required, must also exist in 
                    <a href="https://admin.foxycart.com/admin.php?ThisAction=ManageProductCategories" target="_blank">
                        FoxyCart Categories
                    </a>.
                    Used to set category specific options like shipping and taxes. Managed in
                        <a href="admin/settings">
                            Settings > FoxyStripe > Categories
                        </a>'
            ));
        if (class_exists('QuickAddNewExtension')) {
            $catField->useAddNew('ProductCategory', $source);
        }

        // Product Images gridfield
        $config = GridFieldConfig_RelationEditor::create();
        if (class_exists('GridFieldSortableRows')) {
            $config->addComponent(new GridFieldSortableRows('SortOrder'));
        }
        if (class_exists('GridFieldBulkImageUpload')) {
            $config->addComponent(new GridFieldBulkUpload());
            $config->getComponentByType('GridFieldBulkUpload')->setUfConfig('folderName', 'Uploads/ProductImages');
        }
        $prodImagesField = GridField::create(
            'ProductImages',
            _t('ProductPage.ProductImages', 'Images'),
            $this->ProductImages(),
            $config
        );

        // Product Options field
        $config = GridFieldConfig_RelationEditor::create();
        if (class_exists('GridFieldBulkManager')) {
            $config->addComponent(new GridFieldBulkManager());
        }
        if (class_exists('GridFieldSortableRows')) {
            $config->addComponent(new GridFieldSortableRows('SortOrder'));
            $products = $this->ProductOptions()->sort('SortOrder');
        } else {
            $products = $this->ProductOptions();
        }
        $config->removeComponentsByType('GridFieldAddExistingAutocompleter');
        $prodOptField = GridField::create(
            'ProductOptions',
            _t('ProductPage.ProductOptions', 'Options'),
            $products,
            $config
        );

        // Details tab
        $fields->addFieldsToTab('Root.Details', array(
            HeaderField::create('DetailHD', 'Product Details', 2),
            CheckboxField::create('Available')
                ->setTitle(_t('ProductPage.Available', 'Available for purchase'))
                ->setDescription(_t(
                    'ProductPage.AvailableDescription',
                    'If unchecked, will remove "Add to Cart" form and instead display "Currently unavailable"'
                )),
            TextField::create('Code')
                ->setTitle(_t('ProductPage.Code', 'Product Code'))
                ->setDescription(_t(
                    'ProductPage.CodeDescription',
                    'Required, must be unique. Product identifier used by FoxyCart in transactions'
                )),
            $catField,
            CurrencyField::create('Price')
                ->setTitle(_t('ProductPage.Price', 'Price'))
                ->setDescription(_t(
                    'ProductPage.PriceDescription',
                    'Base price for this product. Can be modified using Product Options'
                )),
            NumericField::create('Weight')
                ->setTitle(_t('ProductPage.Weight', 'Weight'))
                ->setDescription(_t(
                    'ProductPage.WeightDescription',
                    'Base weight for this product in lbs. Can be modified using Product Options'
                )),
            CheckboxField::create('Featured')
                ->setTitle(_t('ProductPage.Featured', 'Featured Product')),
            TextField::create('ReceiptTitle')
                ->setTitle(_t('ProductPage.ReceiptTitle', 'Product Title for Receipt'))
                ->setDescription(_t(
                    'ProductPage.ReceiptTitleDescription', 'Optional'
                ))
        ));

        // Images tab
        $fields->addFieldsToTab('Root.Images', array(
            HeaderField::create('MainImageHD', _t('ProductPage.MainImageHD', 'Product Image'), 2),
            UploadField::create('PreviewImage', '')
                ->setDescription($previewDescription)
                ->setFolderName('Uploads/Products')
                ->setAllowedExtensions(array('jpg', 'jpeg', 'gif', 'png'))
                ->setAllowedMaxFileNumber(1),
            HeaderField::create('ProductImagesHD', _t('ProductPage.ProductImagesHD' . 'Product Image Gallery'), 2),
            $prodImagesField
                ->setDescription(_t(
                    'ProductPage.ProductImagesDescription',
                    'Additional Product Images, shown in gallery on Product page'
                ))
        ));

        // Options Tab
        $fields->addFieldsToTab('Root.Options', array(
            HeaderField::create('OptionsHD', _t('ProductPage.OptionsHD', 'Product Options'), 2),
            LiteralField::create('OptionsDescrip', _t(
                'Page.OptionsDescrip',
                '<p>Product Options allow products to be customized by attributes such as size or color.
                    Options can also modify the product\'s price, weight or code.</p>'
            )),
            $prodOptField
        ));

        if (FoxyCart::store_name_warning() !== null) {
            $fields->addFieldToTab('Root.Main', LiteralField::create("StoreSubDomainHeaderWarning", _t(
                'ProductPage.StoreSubDomainHeaderWarning',
                "<p class=\"message error\">Store sub-domain must be entered in the <a href=\"/admin/settings/\">site settings</a></p>"
            )), 'Title');
        }

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->CategoryID) {
            $default = ProductCategory::get()->filter(array('Code' => 'DEFAULT'))->first();
            $this->CategoryID = $default->ID;
        }

        //update many_many lists when multi-group is on
        if (SiteConfig::current_site_config()->MultiGroup) {
            $holders = $this->ProductHolders();
            $product = ProductPage::get()->byID($this->ID);
            if (isset($product->ParentID)) {
                $origParent = $product->ParentID;
            } else {
                $origParent = null;
            }
            $currentParent = $this->ParentID;
            if ($origParent != $currentParent) {
                if ($holders->find('ID', $origParent)) {
                    $holders->removeByID($origParent);
                }

            }
            $holders->add($currentParent);
        }

        $title = ltrim($this->Title);
        $title = rtrim($title);
        $this->Title = $title;


    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();


    }

    public function onBeforeDelete()
    {
        if ($this->Status != "Published") {
            if ($this->ProductOptions()) {
                $options = $this->getComponents('ProductOptions');
                foreach ($options as $option) {
                    $option->delete();
                }
            }
            if ($this->ProductImages()) {
                //delete product image dataobjects, not the images themselves.
                $images = $this->getComponents('ProductImages');
                foreach ($images as $image) {
                    $image->delete();
                }
            }
        }
        parent::onBeforeDelete();
    }

    public function validate()
    {
        $result = parent::validate();

        /*if($this->ID>0){
            if($this->Price <= 0) {
                $result->error('Must set a positive price value');
            }
            if($this->Weight <= 0){
                $result->error('Must set a positive weight value');
            }
            if($this->Code == ''){
                $result->error('Must set a product code');
            }
        }*/

        return $result;
    }

    public function getCMSValidator()
    {
        return new RequiredFields(array('CategoryID', 'Price', 'Weight', 'Code'));
    }

    public static function getGeneratedValue(
        $productCode = null,
        $optionName = null,
        $optionValue = null,
        $method = 'name',
        $output = false,
        $urlEncode = false
    ) {
        $optionName = ($optionName !== null) ? preg_replace('/\s/', '_', $optionName) : $optionName;
        return (SiteConfig::current_site_config()->CartValidation)
            ? FoxyCart_Helper::fc_hash_value($productCode, $optionName, $optionValue, $method, $output, $urlEncode) :
            $optionValue;
    }

    // get FoxyCart Store Name for JS call
    public function getCartScript()
    {
        return '<script src="https://cdn.foxycart.com/' . FoxyCart::getFoxyCartStoreName() . '/loader.js" async defer></script>';
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        return Permission::check('Product_CANCRUD');
    }

    public function canDelete($member = null)
    {
        return Permission::check('Product_CANCRUD');
    }

    public function canCreate($member = null)
    {
        return Permission::check('Product_CANCRUD');
    }

    public function canPublish($member = null)
    {
        return Permission::check('Product_CANCRUD');
    }

    public function providePermissions()
    {
        return array(
            'Product_CANCRUD' => 'Allow user to manage Products and related objects'
        );
    }

}

class ProductPage_Controller extends Page_Controller
{

    private static $allowed_actions = array(
        'PurchaseForm'
    );

    public function init()
    {
        parent::init();
        Requirements::javascript("framework/thirdparty/jquery/jquery.js");
        if ($this->data()->Available && $this->ProductOptions()->exists()) {
            $formName = $this->PurchaseForm()->FormName();
            Requirements::javascriptTemplate(
                "foxystripe/javascript/out_of_stock.js",
                [
                    'FormName' => $formName,
                ],
                'foxystripe.out_of_stock'
            );
            Requirements::javascriptTemplate(
                'foxystripe/javascript/product_options.js',
                [
                    'FormName' => $formName,
                ],
                'foxystripe.product_options'
            );
        }

        Requirements::customScript(<<<JS
		var productID = {$this->data()->ID};
JS
        );
    }

    /**
     * @return FoxyStripePurchaseForm
     */
    public function PurchaseForm()
    {

        $form = FoxyStripePurchaseForm::create($this, __FUNCTION__, null, null, null, $this->data());

        $this->extend('updateFoxyStripePurchaseForm', $form);

        return $form;

    }
}
