<?php

namespace eagle\modules\order\models;

use Yii;

/**
 * This is the model class for table "od_order_item".
 *
 * @property string $order_item_id
 * @property string $order_id
 * @property string $order_source_srn
 * @property string $order_source_order_item_id
 * @property string $sku
 * @property string $product_name
 * @property string $photo_primary
 * @property string $shipping_price
 * @property string $shipping_discount
 * @property string $price
 * @property string $promotion_discount
 * @property string $ordered_quantity
 * @property string $quantity
 * @property string $sent_quantity
 * @property string $packed_quantity
 * @property string $returned_quantity
 * @property string $invoice_requirement
 * @property string $buyer_selected_invoice_category
 * @property string $invoice_title
 * @property string $invoice_information
 * @property string $create_time
 * @property string $update_time
 */
class OdOrderItemOld extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'od_order_item_old_v2';
    }

    /**
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->get('subdb');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['order_item_id','order_id', 'order_source_srn', 'ordered_quantity', 'quantity', 'sent_quantity', 'packed_quantity', 'returned_quantity', 'create_time', 'update_time'], 'integer'],
            [['shipping_price', 'shipping_discount', 'price', 'promotion_discount'], 'number'],            
            [[ 'invoice_requirement', 'buyer_selected_invoice_category', 'invoice_title', 'invoice_information'], 'string', 'max' => 50],
			[['photo_primary'], 'string', 'max' => 455],
            [['product_name','sku'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'order_item_id' => '????????????id',
            'order_id' => '?????????',
            'order_source_srn' => 'od_ebay_transaction???salesrecordnum',
            'order_source_order_item_id' => 'od_ebay_transaction???id???amazon???OrderItemId',
            'sku' => '????????????',
            'product_name' => '???????????????',
            'photo_primary' => '??????????????????',
            'shipping_price' => '??????',
            'shipping_discount' => '????????????',
            'price' => '???????????????',
            'promotion_discount' => '????????????',
            'ordered_quantity' => '?????????????????????',
            'quantity' => '????????????????????????',
            'sent_quantity' => '???????????????',
            'packed_quantity' => '???????????????',
            'returned_quantity' => '????????????',
            'invoice_requirement' => '????????????',
            'buyer_selected_invoice_category' => '????????????',
            'invoice_title' => '????????????',
            'invoice_information' => '????????????',
            'create_time' => '????????????',
            'update_time' => '????????????',
        ];
    }
}
