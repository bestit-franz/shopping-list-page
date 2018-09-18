<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerShop\Yves\ShoppingListPage\Form\DataProvider;

use ArrayObject;
use Generated\Shared\Transfer\CompanyBusinessUnitCollectionTransfer;
use Generated\Shared\Transfer\CompanyBusinessUnitCriteriaFilterTransfer;
use Generated\Shared\Transfer\CompanyBusinessUnitTransfer;
use Generated\Shared\Transfer\CompanyUserCollectionTransfer;
use Generated\Shared\Transfer\CompanyUserCriteriaFilterTransfer;
use Generated\Shared\Transfer\CompanyUserTransfer;
use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\FilterTransfer;
use Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer;
use Generated\Shared\Transfer\ShoppingListCompanyUserTransfer;
use Generated\Shared\Transfer\ShoppingListTransfer;
use SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCompanyBusinessUnitClientInterface;
use SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCompanyUserClientInterface;
use SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCustomerClientInterface;
use SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToShoppingListClientInterface;
use SprykerShop\Yves\ShoppingListPage\Form\ShareShoppingListForm;

class ShareShoppingListDataProvider
{
    protected const GLOSSARY_KEY_PERMISSIONS = 'customer.account.shopping_list.permissions';

    protected const ORDER_BUSINESS_UNIT_SORT_FIELD = 'name';
    protected const ORDER_BUSINESS_UNIT_SORT_DIRECTION = 'ASC';

    protected const PERMISSION_NO_ACCESS = 'NO_ACCESS';

    /**
     * @var \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCompanyBusinessUnitClientInterface
     */
    protected $companyBusinessUnitClient;

    /**
     * @var \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCompanyUserClientInterface
     */
    protected $companyUserClient;

    /**
     * @var \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCustomerClientInterface
     */
    protected $customerClient;

    /**
     * @var \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToShoppingListClientInterface
     */
    protected $shoppingListClient;

    /**
     * @param \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCompanyBusinessUnitClientInterface $companyBusinessUnitClient
     * @param \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCompanyUserClientInterface $companyUserClient
     * @param \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToCustomerClientInterface $customerClient
     * @param \SprykerShop\Yves\ShoppingListPage\Dependency\Client\ShoppingListPageToShoppingListClientInterface $shoppingListClient
     */
    public function __construct(ShoppingListPageToCompanyBusinessUnitClientInterface $companyBusinessUnitClient, ShoppingListPageToCompanyUserClientInterface $companyUserClient, ShoppingListPageToCustomerClientInterface $customerClient, ShoppingListPageToShoppingListClientInterface $shoppingListClient)
    {
        $this->companyBusinessUnitClient = $companyBusinessUnitClient;
        $this->companyUserClient = $companyUserClient;
        $this->customerClient = $customerClient;
        $this->shoppingListClient = $shoppingListClient;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer
     */
    public function getData(ShoppingListTransfer $shoppingListTransfer): ShoppingListTransfer
    {
        $customerTransfer = $this->getCustomer();
        $shoppingListTransfer->setIdCompanyUser($customerTransfer->getCompanyUserTransfer()->getIdCompanyUser());

        $shoppingListTransfer = $this->shoppingListClient->getShoppingList($shoppingListTransfer);

        $shoppingListTransfer = $this->expandSharedCompanyUsers($shoppingListTransfer, $customerTransfer);
        $this->sortShoppingListCompanyUsers($shoppingListTransfer);

        $shoppingListTransfer = $this->expandSharedCompanyBusinessUnits($shoppingListTransfer, $customerTransfer);
        $this->sortShoppingListCompanyBusinessUnit($shoppingListTransfer);

        return $shoppingListTransfer;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return [
            ShareShoppingListForm::OPTION_PERMISSION_GROUPS => $this->getShoppingListPermissionGroups(),
        ];
    }

    /**
     * @return \Generated\Shared\Transfer\CustomerTransfer
     */
    protected function getCustomer(): CustomerTransfer
    {
        return $this->customerClient->getCustomer();
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer
     */
    protected function expandSharedCompanyUsers(
        ShoppingListTransfer $shoppingListTransfer,
        CustomerTransfer $customerTransfer
    ): ShoppingListTransfer {
        $sharedCompanyUsers = $this->indexSharedCompanyUsers($shoppingListTransfer);
        $companyUsers = $this->getCompanyUserCollection($customerTransfer)->getCompanyUsers();

        foreach ($companyUsers as $companyUserTransfer) {
            if (strcmp($companyUserTransfer->getCustomer()->getCustomerReference(), $shoppingListTransfer->getCustomerReference()) === 0) {
                continue;
            }

            $sharedCompanyUsers[$companyUserTransfer->getIdCompanyUser()] = $this->getSharedByCompanyUser(
                $shoppingListTransfer,
                $companyUserTransfer,
                $sharedCompanyUsers
            );
        }

        $shoppingListTransfer->setSharedCompanyUsers(new ArrayObject($sharedCompanyUsers));

        return $shoppingListTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyUserTransfer[]
     */
    protected function indexSharedCompanyUsers(ShoppingListTransfer $shoppingListTransfer): array
    {
        $sharedCompanyUsers = [];

        foreach ($shoppingListTransfer->getSharedCompanyUsers() as $shoppingListCompanyUserTransfer) {
            $sharedCompanyUsers[$shoppingListCompanyUserTransfer->getIdCompanyUser()] = $shoppingListCompanyUserTransfer;
        }

        return $sharedCompanyUsers;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     * @param \Generated\Shared\Transfer\CompanyUserTransfer $companyUserTransfer
     * @param \Generated\Shared\Transfer\ShoppingListCompanyUserTransfer[] $sharedCompanyUsers
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyUserTransfer
     */
    protected function getSharedByCompanyUser(
        ShoppingListTransfer $shoppingListTransfer,
        CompanyUserTransfer $companyUserTransfer,
        $sharedCompanyUsers
    ): ShoppingListCompanyUserTransfer {
        if (isset($sharedCompanyUsers[$companyUserTransfer->getIdCompanyUser()])) {
            return $sharedCompanyUsers[$companyUserTransfer->getIdCompanyUser()]->setCompanyUser($companyUserTransfer);
        }

        return (new ShoppingListCompanyUserTransfer())
            ->setIdCompanyUser($companyUserTransfer->getIdCompanyUser())
            ->setIdShoppingList($shoppingListTransfer->getIdShoppingList())
            ->setCompanyUser($companyUserTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return void
     */
    protected function sortShoppingListCompanyUsers(ShoppingListTransfer $shoppingListTransfer): void
    {
        $shoppingListTransfer->getSharedCompanyUsers()->uasort(
            function (ShoppingListCompanyUserTransfer $firstUserTransfer, ShoppingListCompanyUserTransfer $secondUserTransfer) {
                return strcmp($firstUserTransfer->getCompanyUser()->getCustomer()->getFirstName(), $secondUserTransfer->getCompanyUser()->getCustomer()->getFirstName());
            }
        );
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer
     */
    protected function expandSharedCompanyBusinessUnits(
        ShoppingListTransfer $shoppingListTransfer,
        CustomerTransfer $customerTransfer
    ): ShoppingListTransfer {
        $sharedCompanyBusinessUnits = $this->indexSharedCompanyBusinessUnits($shoppingListTransfer);
        $companyBusinessUnits = $this->getCompanyBusinessUnitCollection($customerTransfer)->getCompanyBusinessUnits();

        foreach ($companyBusinessUnits as $companyBusinessUnitTransfer) {
            $sharedCompanyBusinessUnits[$companyBusinessUnitTransfer->getIdCompanyBusinessUnit()] = $this->getSharedByCompanyBusinessUnit(
                $shoppingListTransfer,
                $companyBusinessUnitTransfer,
                $sharedCompanyBusinessUnits
            );
        }

        $shoppingListTransfer->setSharedCompanyBusinessUnits(new ArrayObject($sharedCompanyBusinessUnits));

        return $shoppingListTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer[]
     */
    protected function indexSharedCompanyBusinessUnits(ShoppingListTransfer $shoppingListTransfer): array
    {
        $sharedCompanyBusinessUnits = [];

        foreach ($shoppingListTransfer->getSharedCompanyBusinessUnits() as $shoppingListCompanyBusinessUnitTransfer) {
            $sharedCompanyBusinessUnits[$shoppingListCompanyBusinessUnitTransfer->getIdCompanyBusinessUnit()] = $shoppingListCompanyBusinessUnitTransfer;
        }

        return $sharedCompanyBusinessUnits;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     * @param \Generated\Shared\Transfer\CompanyBusinessUnitTransfer $companyBusinessUnitTransfer
     * @param \Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer[] $sharedCompanyBusinessUnits
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer
     */
    protected function getSharedByCompanyBusinessUnit(
        ShoppingListTransfer $shoppingListTransfer,
        CompanyBusinessUnitTransfer $companyBusinessUnitTransfer,
        $sharedCompanyBusinessUnits
    ): ShoppingListCompanyBusinessUnitTransfer {
        if (isset($sharedCompanyBusinessUnits[$companyBusinessUnitTransfer->getIdCompanyBusinessUnit()])) {
            return $sharedCompanyBusinessUnits[$companyBusinessUnitTransfer->getIdCompanyBusinessUnit()]->setCompanyBusinessUnit($companyBusinessUnitTransfer);
        }

        return (new ShoppingListCompanyBusinessUnitTransfer())
            ->setIdCompanyBusinessUnit($companyBusinessUnitTransfer->getIdCompanyBusinessUnit())
            ->setIdShoppingList($shoppingListTransfer->getIdShoppingList())
            ->setCompanyBusinessUnit($companyBusinessUnitTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return void
     */
    protected function sortShoppingListCompanyBusinessUnit(ShoppingListTransfer $shoppingListTransfer): void
    {
        $shoppingListTransfer->getSharedCompanyBusinessUnits()->uasort(
            function (ShoppingListCompanyBusinessUnitTransfer $firstBusinessUnitTransfer, ShoppingListCompanyBusinessUnitTransfer $secondBusinessUnitTransfer) {
                return strcmp($firstBusinessUnitTransfer->getCompanyBusinessUnit()->getName(), $secondBusinessUnitTransfer->getCompanyBusinessUnit()->getName());
            }
        );
    }

    /**
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     *
     * @return \Generated\Shared\Transfer\CompanyBusinessUnitCollectionTransfer
     */
    protected function getCompanyBusinessUnitCollection(CustomerTransfer $customerTransfer): CompanyBusinessUnitCollectionTransfer
    {
        $idCompany = $customerTransfer->requireCompanyUserTransfer()->getCompanyUserTransfer()->getFkCompany();
        $filter = (new FilterTransfer())
            ->setOrderBy(static::ORDER_BUSINESS_UNIT_SORT_FIELD)
            ->setOrderDirection(static::ORDER_BUSINESS_UNIT_SORT_DIRECTION);

        $companyBusinessUnitCriteriaFilterTransfer = (new CompanyBusinessUnitCriteriaFilterTransfer())
            ->setIdCompany($idCompany)
            ->setFilter($filter);

        return $this->companyBusinessUnitClient->getCompanyBusinessUnitCollection($companyBusinessUnitCriteriaFilterTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     *
     * @return \Generated\Shared\Transfer\CompanyUserCollectionTransfer
     */
    protected function getCompanyUserCollection(CustomerTransfer $customerTransfer): CompanyUserCollectionTransfer
    {
        $companyUserCriteriaFilterTransfer = (new CompanyUserCriteriaFilterTransfer())
            ->setIdCompany($customerTransfer->requireCompanyUserTransfer()->getCompanyUserTransfer()->getFkCompany());

        return $this->companyUserClient->getCompanyUserCollection($companyUserCriteriaFilterTransfer);
    }

    /**
     * @return \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer[]
     */
    protected function getShoppingListPermissionGroups(): array
    {
        $shoppingListPermissionGroups = $this->shoppingListClient
            ->getShoppingListPermissionGroups()
            ->getPermissionGroups();

        return $this->mapPermissionGroupsToOptions($shoppingListPermissionGroups);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer[]|\ArrayObject $permissionGroups
     *
     * @return array
     */
    protected function mapPermissionGroupsToOptions(ArrayObject $permissionGroups): array
    {
        $permissionGroupOptions = [static::GLOSSARY_KEY_PERMISSIONS . '.' . static::PERMISSION_NO_ACCESS => 0];
        foreach ($permissionGroups as $permissionGroupTransfer) {
            $permissionGroupOptions[static::GLOSSARY_KEY_PERMISSIONS . '.' . $permissionGroupTransfer->getName()]
                = $permissionGroupTransfer->getIdShoppingListPermissionGroup();
        }

        return $permissionGroupOptions;
    }
}
