<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerShop\Yves\ShoppingListPage\Controller;

use Generated\Shared\Transfer\ProductViewTransfer;
use Generated\Shared\Transfer\ShoppingListItemCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListItemTransfer;
use Generated\Shared\Transfer\ShoppingListOverviewRequestTransfer;
use Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer;
use Generated\Shared\Transfer\ShoppingListTransfer;
use Spryker\Yves\Kernel\View\View;
use SprykerShop\Yves\ShoppingListPage\Plugin\Provider\ShoppingListPageControllerProvider;
use SprykerShop\Yves\ShoppingListPage\ShoppingListPageConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method \SprykerShop\Yves\ShoppingListPage\ShoppingListPageFactory getFactory()
 */
class ShoppingListController extends AbstractShoppingListController
{
    protected const PARAM_ITEMS_PER_PAGE = 'ipp';
    protected const PARAM_PAGE = 'page';
    protected const PARAM_SKU = 'sku';
    protected const PARAM_QUANTITY = 'quantity';
    protected const PARAM_ID_SHOPPING_LIST_ITEM = 'idShoppingListItem';
    protected const PARAM_SHOPPING_LIST_ITEM = 'shoppingListItem';
    protected const PARAM_ID_SHOPPING_LIST = 'idShoppingList';

    /**
     * @param int $idShoppingList
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return \Spryker\Yves\Kernel\View\View
     */
    public function indexAction(int $idShoppingList, Request $request): View
    {
        $pageNumber = $this->getPageNumber($request);
        $itemsPerPage = $this->getItemsPerPage($request);

        $shoppingListTransfer = (new ShoppingListTransfer())
            ->setIdShoppingList($idShoppingList)
            ->setIdCompanyUser($this->getCustomer()->getCompanyUserTransfer()->getIdCompanyUser());

        $shoppingListOverviewRequest = (new ShoppingListOverviewRequestTransfer())
            ->setShoppingList($shoppingListTransfer)
            ->setPage($pageNumber)
            ->setItemsPerPage($itemsPerPage);

        $shoppingListOverviewResponseTransfer = $this->getFactory()
            ->getShoppingListClient()
            ->getShoppingListOverviewWithoutProductDetails($shoppingListOverviewRequest);

        if (!$shoppingListOverviewResponseTransfer->getShoppingList()->getIdShoppingList()) {
            throw new NotFoundHttpException();
        }

        $shoppingListItems = $this->getShoppingListItems($shoppingListOverviewResponseTransfer);

        $data = [
            'shoppingListItems' => $shoppingListItems,
            'shoppingListOverview' => $shoppingListOverviewResponseTransfer,
            'currentPage' => $shoppingListOverviewResponseTransfer->getPagination()->getPage(),
            'totalPages' => $shoppingListOverviewResponseTransfer->getPagination()->getLastPage(),
        ];

        return $this->view($data, [], '@ShoppingListPage/views/shopping-list/shopping-list.twig');
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeItemAction(Request $request): RedirectResponse
    {
        $shoppingListItemTransfer = $this->getShoppingListItemTransferFromRequest($request);

        $shoppingListItemResponseTransfer = $this->getFactory()
            ->getShoppingListClient()
            ->removeItemById($shoppingListItemTransfer);

        if (!$shoppingListItemResponseTransfer->getIsSuccess()) {
            $this->addErrorMessage('customer.account.shopping_list.item.remove.failed');

            return $this->redirectResponseInternal(ShoppingListPageControllerProvider::ROUTE_SHOPPING_LIST_DETAILS, [
                'idShoppingList' => $shoppingListItemTransfer->getFkShoppingList(),
            ]);
        }

        $this->addSuccessMessage('customer.account.shopping_list.item.remove.success');

        return $this->redirectResponseInternal(ShoppingListPageControllerProvider::ROUTE_SHOPPING_LIST_DETAILS, [
            'idShoppingList' => $shoppingListItemTransfer->getFkShoppingList(),
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addToCartAction(Request $request): RedirectResponse
    {
        $shoppingListItemTransfer = $this->getShoppingListItemTransferFromRequest($request);

        $result = $this->getFactory()
            ->createAddToCartHandler()
            ->addAllAvailableToCart([$shoppingListItemTransfer]);

        if ($result->getRequests()->count()) {
            $this->addErrorMessage('customer.account.shopping_list.item.added_to_cart.failed');
            return $this->redirectResponseInternal(ShoppingListPageControllerProvider::ROUTE_SHOPPING_LIST_DETAILS, [
                'idShoppingList' => $shoppingListItemTransfer->getFkShoppingList(),
            ]);
        }

        $this->addSuccessMessage('customer.account.shopping_list.item.added_to_cart');
        return $this->redirectResponseInternal(ShoppingListPageConfig::CART_REDIRECT_URL, [
            'idShoppingList' => $shoppingListItemTransfer->getFkShoppingList(),
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function multiAddToCartAction(Request $request): RedirectResponse
    {
        $shoppingListItemTransferCollection = $this->getShoppingListItemCollectionTransferFromRequest($request);
        if (count($shoppingListItemTransferCollection->getItems())  === 0) {
            $this->addErrorMessage('customer.account.shopping_list.item.select_item');
            return $this->redirectResponseInternal(ShoppingListPageControllerProvider::ROUTE_SHOPPING_LIST_DETAILS, [
                'idShoppingList' => $request->get(static::PARAM_ID_SHOPPING_LIST),
            ]);
        }

        $shoppingListItemCollectionTransfer = $this->getFactory()
            ->getShoppingListClient()
            ->getShoppingListItemCollectionTransfer($shoppingListItemTransferCollection);

        $quantity = $request->get(static::PARAM_SHOPPING_LIST_ITEM)[static::PARAM_QUANTITY] ?? [];
        $result = $this->getFactory()
            ->createAddToCartHandler()
            ->addAllAvailableToCart($shoppingListItemCollectionTransfer->getItems()->getArrayCopy(), $quantity);

        if ($result->getRequests()->count()) {
            $this->addErrorMessage('customer.account.shopping_list.item.added_to_cart.failed');
            return $this->redirectResponseInternal(ShoppingListPageControllerProvider::ROUTE_SHOPPING_LIST_DETAILS, [
                'idShoppingList' => $request->get(static::PARAM_ID_SHOPPING_LIST),
            ]);
        }

        $this->addSuccessMessage('customer.account.shopping_list.item.added_to_cart');
        return $this->redirectResponseInternal(ShoppingListPageConfig::CART_REDIRECT_URL, [
            'idShoppingList' => $request->get(static::PARAM_ID_SHOPPING_LIST),
        ]);
    }

    /**
     * @param int $idShoppingList
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addAvailableProductsToCartAction(int $idShoppingList): RedirectResponse
    {
        $shoppingListTransfer = (new ShoppingListTransfer())
            ->setIdShoppingList($idShoppingList)
            ->setIdCompanyUser($this->getCustomer()->getCompanyUserTransfer()->getIdCompanyUser());

        $shoppingListOverviewRequest = (new ShoppingListOverviewRequestTransfer())
            ->setShoppingList($shoppingListTransfer);

        $shoppingListOverviewResponseTransfer = $this->getFactory()
            ->getShoppingListClient()
            ->getShoppingListOverviewWithoutProductDetails($shoppingListOverviewRequest);

        if (!$shoppingListOverviewResponseTransfer->getShoppingList()->getIdShoppingList()) {
            throw new NotFoundHttpException();
        }

        $result = $this->getFactory()
            ->createAddToCartHandler()
            ->addAllAvailableToCart((array)$shoppingListOverviewResponseTransfer->getItemsCollection()->getItems());

        if ($result->getRequests()->count()) {
            $this->addErrorMessage('customer.account.shopping_list.item.added_all_available_to_cart.failed');
            return $this->redirectResponseInternal(ShoppingListPageControllerProvider::ROUTE_SHOPPING_LIST_DETAILS, [
                'idShoppingList' => $idShoppingList,
            ]);
        }

        $this->addSuccessMessage('customer.account.shopping_list.item.added_all_available_to_cart');
        return $this->redirectResponseInternal(ShoppingListPageConfig::CART_REDIRECT_URL, [
            'idShoppingList' => $idShoppingList,
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return int
     */
    protected function getPageNumber(Request $request): int
    {
        $pageNumber = $request->query->getInt(static::PARAM_PAGE, 1);
        $pageNumber = $pageNumber <= 0 ? 1 : $pageNumber;

        return $pageNumber;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return int
     */
    protected function getItemsPerPage(Request $request): int
    {
        $itemsPerPage = $request->query->getInt(static::PARAM_ITEMS_PER_PAGE, $this->getFactory()->getBundleConfig()->getShoppingListDefaultItemsPerPage());
        $itemsPerPage = ($itemsPerPage <= 0) ? 1 : $itemsPerPage;
        $itemsPerPage = ($itemsPerPage > 100) ? 10 : $itemsPerPage;

        return $itemsPerPage;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemTransfer
     */
    protected function getShoppingListItemTransferFromRequest(Request $request): ShoppingListItemTransfer
    {
        $shoppingListItemTransfer = (new ShoppingListItemTransfer())
            ->setSku($request->get(static::PARAM_SKU))
            ->setQuantity((int)$request->get(static::PARAM_QUANTITY))
            ->setFkShoppingList($request->get(static::PARAM_ID_SHOPPING_LIST))
            ->setIdCompanyUser($this->getCustomer()->getCompanyUserTransfer()->getIdCompanyUser());

        $requestIdShoppingListItem = (int)$request->get(static::PARAM_ID_SHOPPING_LIST_ITEM);
        if ($requestIdShoppingListItem) {
            $shoppingListItemTransfer->setIdShoppingListItem($requestIdShoppingListItem);
        }

        return $shoppingListItemTransfer;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemCollectionTransfer
     */
    protected function getShoppingListItemCollectionTransferFromRequest(Request $request): ShoppingListItemCollectionTransfer
    {
        $shoppingListCollectionTransfer = new ShoppingListItemCollectionTransfer();
        $shoppingListItemRequest = $request->get(static::PARAM_SHOPPING_LIST_ITEM);
        if (!empty($shoppingListItemRequest[self::PARAM_ID_SHOPPING_LIST_ITEM])) {
            foreach ($shoppingListItemRequest[self::PARAM_ID_SHOPPING_LIST_ITEM] as $idShoppingListItem) {
                $shoppingListItemTransfer = (new ShoppingListItemTransfer())
                    ->setIdShoppingListItem((int)$idShoppingListItem)
                    ->setFkShoppingList($request->request->getInt(static::PARAM_ID_SHOPPING_LIST));

                $shoppingListCollectionTransfer->addItem($shoppingListItemTransfer);
            }
        }

        return $shoppingListCollectionTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer $shoppingListOverviewResponseTransfer
     *
     * @return \Generated\Shared\Transfer\ProductViewTransfer[]
     */
    protected function getShoppingListItems(ShoppingListOverviewResponseTransfer $shoppingListOverviewResponseTransfer): array
    {
        $shoppingListItems = [];
        if ($shoppingListOverviewResponseTransfer->getItemsCollection()) {
            foreach ($shoppingListOverviewResponseTransfer->getItemsCollection()->getItems() as $item) {
                $shoppingListItems[] = $this->createProductView($item);
            }
        }

        return $shoppingListItems;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ProductViewTransfer
     */
    protected function createProductView(ShoppingListItemTransfer $shoppingListItemTransfer): ProductViewTransfer
    {
        $productConcreteStorageData = $this->getFactory()
            ->getProductStorageClient()
            ->getProductConcreteStorageData($shoppingListItemTransfer->getIdProduct(), $this->getLocale());

        $productViewTransfer = new ProductViewTransfer();
        if (empty($productConcreteStorageData)) {
            $productConcreteStorageData = [
                'sku' => $shoppingListItemTransfer->getSku(),
            ];
        }
        $productViewTransfer->fromArray($productConcreteStorageData, true);

        foreach ($this->getFactory()->getShoppingListItemExpanderPlugins() as $productViewExpanderPlugin) {
            $productViewTransfer = $productViewExpanderPlugin->expandProductViewTransfer(
                $productViewTransfer,
                $productConcreteStorageData,
                $this->getLocale()
            );

            $productViewTransfer->setQuantity($shoppingListItemTransfer->getQuantity());
            $productViewTransfer->setIdShoppingListItem($shoppingListItemTransfer->getIdShoppingListItem());
        }

        return $productViewTransfer;
    }
}
