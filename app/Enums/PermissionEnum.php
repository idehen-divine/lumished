<?php

namespace App\Enums;

enum PermissionEnum
{
    case ACCESS_ADMIN_PANEL;
    case ACCESS_CUSTOMER_PANEL;

    case ADD_PRODUCTS;
    case VIEW_PRODUCTS;
    case EDIT_PRODUCTS;
    case DELETE_PRODUCTS;

    case VIEW_CATEGORIES;
    case EDIT_CATEGORIES;
    case DELETE_CATEGORIES;

    case VIEW_BRANDS;
    case EDIT_BRANDS;
    case DELETE_BRANDS;

    case VIEW_CUSTOMERS;
    case EDIT_CUSTOMERS;
    case DELETE_CUSTOMERS;

    case VIEW_PERMISSION;
    case EDIT_PERMISSION;

    case VIEW_SETTINGS;
    case EDIT_SETTINGS;
    case DELETE_SETTINGS;

    case VIEW_LOGS;
    case DELETE_LOGS;

    case MANAGE_ORDERS;
    case VIEW_ORDERS;

    case MANAGE_OWN_STORES;
    case MANAGE_OWN_PRODUCTS;
    case MANAGE_OWN_CATEGORIES;

    case VIEW_STORES;
    case MANAGE_STORES;
}
