<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AttributeGroup\Repository;

use AttributeGroup;
use Doctrine\DBAL\Connection;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\InvalidShopConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShop\PrestaShop\Core\Repository\AbstractMultiShopObjectModelRepository;

class AttributeGroupRepository extends AbstractMultiShopObjectModelRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $dbPrefix;

    public function __construct(
        Connection $connection,
        string $dbPrefix
    ) {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * @param ShopConstraint $shopConstraint
     *
     * @return array<int, AttributeGroup> array key is the id of attribute group
     */
    public function getAttributeGroups(ShopConstraint $shopConstraint): array
    {
        if ($shopConstraint->getShopGroupId()) {
            throw new InvalidShopConstraintException('Shop Group constraint is not supported');
        }
        $shopIdValue = $shopConstraint->getShopId() ? $shopConstraint->getShopId()->getValue() : null;
        $groupsQb =
            $this->connection->createQueryBuilder()
                ->select('ag.*, agl.*')
                ->from($this->dbPrefix . 'attribute_group', 'ag')
                ->innerJoin(
                    'ag',
                    $this->dbPrefix . 'attribute_group_lang',
                    'agl',
                    'ag.id_attribute_group = agl.id_attribute_group'
                )
                ->orderBy('ag.position', 'ASC')
        ;

        if ($shopIdValue) {
            $groupsQb
                ->innerJoin(
                    'ag',
                    $this->dbPrefix . 'attribute_group_shop',
                    'ags',
                    'ag.id_attribute_group = ags.id_attribute_group'
                )
                ->andWhere('ags.id_shop = :shopId')
                ->setParameter('shopId', $shopIdValue)
            ;
        }

        $results = $groupsQb->execute()->fetchAllAssociative();

        if (!$results) {
            return [];
        }

        $attributeGroups = [];

        foreach ($results as $result) {
            $attributeGroupId = (int) $result['id_attribute_group'];
            $langId = (int) $result['id_lang'];

            if (isset($attributeGroups[$attributeGroupId])) {
                $attributeGroup = $attributeGroups[$attributeGroupId];
            } else {
                $attributeGroup = new AttributeGroup();
                $attributeGroups[$attributeGroupId] = $attributeGroup;
            }

            $attributeGroup->id = $attributeGroupId;
            $attributeGroup->is_color_group = (bool) $result['is_color_group'];
            $attributeGroup->group_type = (string) $result['group_type'];
            $attributeGroup->position = (int) $result['position'];
            $attributeGroup->name[$langId] = (string) $result['name'];
            $attributeGroup->public_name[$langId] = (string) $result['public_name'];
        }

        return $attributeGroups;
    }
}
