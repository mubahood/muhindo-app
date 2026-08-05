<?php

namespace App\Support\Analytics;

/**
 * The vocabulary. Every recorded event uses a name from here.
 *
 * Free-text event names are how an analytics table becomes unqueryable: within
 * a month there is `add_to_cart`, `addToCart`, `cart-add` and `Cart Added`, all
 * meaning the same thing and none of them adding up. A constant is cheap
 * insurance against that.
 *
 * The categories matter more than they look. A funnel is only meaningful if
 * you can say which events are steps toward money and which are just noise,
 * and CONVERSIONS is that line, drawn once, in one place.
 */
final class Events
{
    // Reach
    public const OUTBOUND_CLICK = 'outbound.click';

    public const SEARCH = 'search';

    public const SHARE = 'share';

    // Intent
    public const CTA_CLICK = 'cta.click';

    public const COURSE_PREVIEW = 'course.preview';

    public const PRODUCT_DEMO = 'product.demo';

    public const CART_ADD = 'cart.add';

    public const CART_REMOVE = 'cart.remove';

    public const CHECKOUT_START = 'checkout.start';

    public const CV_DOWNLOAD = 'cv.download';

    // Identity
    public const SIGNUP = 'signup';

    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    // Money and commitment
    public const ENROLLED = 'enrolled';

    public const ORDER_PLACED = 'order.placed';

    public const ORDER_PAID = 'order.paid';

    public const DOWNLOAD = 'download';

    public const INQUIRY = 'inquiry';

    public const CONTACT = 'contact';

    // Learning
    public const LESSON_START = 'lesson.start';

    public const LESSON_COMPLETE = 'lesson.complete';

    public const COURSE_COMPLETE = 'course.complete';

    public const CERTIFICATE_EARNED = 'certificate.earned';

    public const CATEGORY_REACH = 'reach';

    public const CATEGORY_INTENT = 'intent';

    public const CATEGORY_CONVERSION = 'conversion';

    public const CATEGORY_LEARNING = 'learning';

    public const CATEGORY_INTERACTION = 'interaction';

    /** The events that count as an outcome rather than a step toward one. */
    public const CONVERSIONS = [
        self::SIGNUP, self::ENROLLED, self::ORDER_PAID,
        self::INQUIRY, self::CONTACT, self::DOWNLOAD,
    ];

    /**
     * The public funnel, in order. Every step is a superset question: how many
     * got this far. Gaps between two rows are where the money leaks.
     *
     * @var array<string, string>
     */
    public const FUNNEL = [
        'visit' => 'Visited the site',
        self::CTA_CLICK => 'Pressed something',
        self::CART_ADD => 'Put something in a basket',
        self::CHECKOUT_START => 'Started checking out',
        self::SIGNUP => 'Created an account',
        self::ORDER_PAID => 'Paid',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        self::OUTBOUND_CLICK => 'Left for another site',
        self::SEARCH => 'Searched',
        self::SHARE => 'Shared a page',
        self::CTA_CLICK => 'Pressed a call to action',
        self::COURSE_PREVIEW => 'Watched a free preview',
        self::PRODUCT_DEMO => 'Opened a demo',
        self::CART_ADD => 'Added to basket',
        self::CART_REMOVE => 'Removed from basket',
        self::CHECKOUT_START => 'Started checkout',
        self::CV_DOWNLOAD => 'Downloaded the CV',
        self::SIGNUP => 'Created an account',
        self::LOGIN => 'Signed in',
        self::LOGOUT => 'Signed out',
        self::ENROLLED => 'Enrolled on a course',
        self::ORDER_PLACED => 'Placed an order',
        self::ORDER_PAID => 'Paid an invoice',
        self::DOWNLOAD => 'Downloaded a purchase',
        self::INQUIRY => 'Asked for a project',
        self::CONTACT => 'Sent a message',
        self::LESSON_START => 'Started a lesson',
        self::LESSON_COMPLETE => 'Finished a lesson',
        self::COURSE_COMPLETE => 'Finished a course',
        self::CERTIFICATE_EARNED => 'Earned a certificate',
    ];

    /** @var array<string, string> */
    public const ICONS = [
        self::OUTBOUND_CLICK => 'fa-arrow-up-right-from-square',
        self::SEARCH => 'fa-magnifying-glass',
        self::SHARE => 'fa-share-nodes',
        self::CTA_CLICK => 'fa-hand-pointer',
        self::COURSE_PREVIEW => 'fa-circle-play',
        self::PRODUCT_DEMO => 'fa-desktop',
        self::CART_ADD => 'fa-cart-plus',
        self::CART_REMOVE => 'fa-cart-arrow-down',
        self::CHECKOUT_START => 'fa-credit-card',
        self::CV_DOWNLOAD => 'fa-file-arrow-down',
        self::SIGNUP => 'fa-user-plus',
        self::LOGIN => 'fa-right-to-bracket',
        self::LOGOUT => 'fa-right-from-bracket',
        self::ENROLLED => 'fa-user-graduate',
        self::ORDER_PLACED => 'fa-receipt',
        self::ORDER_PAID => 'fa-sack-dollar',
        self::DOWNLOAD => 'fa-download',
        self::INQUIRY => 'fa-briefcase',
        self::CONTACT => 'fa-envelope',
        self::LESSON_START => 'fa-play',
        self::LESSON_COMPLETE => 'fa-circle-check',
        self::COURSE_COMPLETE => 'fa-flag-checkered',
        self::CERTIFICATE_EARNED => 'fa-certificate',
    ];

    /** Names the browser is allowed to report. Anything else is dropped. */
    public const CLIENT_REPORTABLE = [
        self::OUTBOUND_CLICK, self::CTA_CLICK, self::SEARCH, self::SHARE,
        self::COURSE_PREVIEW, self::PRODUCT_DEMO, self::CV_DOWNLOAD,
    ];

    public static function label(string $name): string
    {
        return self::LABELS[$name] ?? ucfirst(str_replace(['.', '_'], ' ', $name));
    }

    public static function icon(string $name): string
    {
        return self::ICONS[$name] ?? 'fa-circle-dot';
    }

    public static function category(string $name): string
    {
        return match (true) {
            in_array($name, self::CONVERSIONS, true) => self::CATEGORY_CONVERSION,
            in_array($name, [self::LESSON_START, self::LESSON_COMPLETE, self::COURSE_COMPLETE, self::CERTIFICATE_EARNED], true) => self::CATEGORY_LEARNING,
            in_array($name, [self::CTA_CLICK, self::CART_ADD, self::CART_REMOVE, self::CHECKOUT_START, self::COURSE_PREVIEW, self::PRODUCT_DEMO, self::CV_DOWNLOAD, self::ORDER_PLACED], true) => self::CATEGORY_INTENT,
            in_array($name, [self::OUTBOUND_CLICK, self::SEARCH, self::SHARE], true) => self::CATEGORY_REACH,
            default => self::CATEGORY_INTERACTION,
        };
    }
}
