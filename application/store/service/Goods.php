<?php

// +----------------------------------------------------------------------
// | framework
// +----------------------------------------------------------------------
// | 版权所有 2014~2018 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://framework.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/framework
// +----------------------------------------------------------------------

namespace app\store\service;

use think\Db;

/**
 * 商品数据管理
 * Class Goods
 * @package app\store\logic
 */
class Goods
{
    /**
     * 同步商品库存信息
     * @param integer $goodsId
     * @return boolean
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function syncStock($goodsId)
    {
        // 商品入库统计
        $fields = "goods_id,goods_spec,ifnull(sum(number_stock),0) number_stock";
        $stockList = Db::name('StoreGoodsStock')->field($fields)->where(['goods_id' => $goodsId])->group('goods_id,goods_spec')->select();
        // 商品销量统计
        $fields = 'goods_id,goods_spec,ifnull(sum(number_goods),0) number_sales';
        $salesList = Db::name('StoreOrderList')->whereIn('order_no', function (\think\db\Query $query) use ($goodsId) {
            $query->name('StoreOrder')->field('order_no')->where(['id' => $goodsId])->whereIn('status', ['1', '2', '3', '4', '5']);
        })->field($fields)->where(['goods_id' => $goodsId])->group('goods_id,goods_spec')->select();
        // 组装更新数据
        $dataList = [];
        foreach (array_merge($stockList, $salesList) as $vo) {
            $key = "{$vo['goods_id']}@@{$vo['goods_spec']}";
            $dataList[$key] = isset($dataList[$key]) ? array_merge($dataList[$key], $vo) : $vo;
            if (empty($dataList[$key]['number_sales'])) $dataList[$key]['number_sales'] = '0';
            if (empty($dataList[$key]['number_stock'])) $dataList[$key]['number_stock'] = '0';
        }
        unset($salesList, $stockList);
        // 更新商品规格销量及库存
        foreach ($dataList as $vo) Db::name('StoreGoodsList')->where([
            'goods_id'   => $goodsId,
            'goods_spec' => $vo['goods_spec'],
        ])->update([
            'number_stock' => $vo['number_stock'],
            'number_sales' => $vo['number_sales'],
        ]);
        // 更新商品主体销量及库存
        Db::name('StoreGoods')->where(['id' => $goodsId])->update([
            'number_stock' => intval(array_sum(array_column($dataList, 'number_stock'))),
            'number_sales' => intval(array_sum(array_column($dataList, 'number_sales'))),
        ]);
        return true;
    }

}