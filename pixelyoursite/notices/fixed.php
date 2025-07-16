<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/*
 * Notice structure
        [
            'order' => '1', // message display order
            'wait' => 0, // timeout after closing the previous message
            'type' => 'event chain', // Message type, if included in the message sequence then type MUST be 'event chain'
            'location' => 'backend', // can be "backend","plugin". backend is show in WP admin on all pages. plugin is show only in plugin page.
            'enabelYoutubeLink' => false, // enables or disables the link to the channel at the bottom of the block
            'enabelLogo' => false, // enable or disable the logo on the left in the block
            'enabelDismiss' => false, // enable or disable dismiss button, default enable
            'color' => 'orange', // color can be 'orange', 'green', 'blue'
            'multiMessage' => [
                [
                    'slug'  => 'new_message_1_v1', // unique slug for message "new_message_1" - unique title, '_v1' - version message
                    'message' => 'Hello I message 1 V 1',
                    'title' => 'Title V1',
                    'button_text' => 'Watch',
                    'button_url' => 'https://www.youtube.com/watch?v=snUKcsTbvCk'
                ],
                [
                    'slug'  => 'new_message_2_v1',
                    'message' => 'Hello I message 2 V 1',
                    'button_text' => 'Watch',
                    'button_url' => 'https://www.youtube.com/watch?v=snUKcsTbvCk',
                ],
                [
                    'slug'  => 'new_message_3_v1',
                    'title' => 'Title V1',
                    'message' => 'Hello I message 3 V 1',
                    'button_text' => 'Watch',
                    'button_url' => 'https://www.youtube.com/watch?v=snUKcsTbvCk',
                ]
            ]
        ],

If need fixed message
        [
            'type' => 'promo',
            'location' => 'backend',
            'enabelDismiss' => false, // enable or disable dismiss button, default enable
            'plugins' =>[], // can be "woo","wcf","edd" or empty array
            'slug'  => '',// unique id
            'message' => '', // message with html tags
        ]
 * */

function adminGetFixedNotices() {
    return [
        [
            'order' => '1',
            'wait' => 0,
            'type' => 'event chain',
            'location' => 'backend',
            'enabelYoutubeLink' => true,
            'enabelLogo' => true,
            'enabelDismiss' => true,
            'color' => 'orange',
            'multiMessage' => [
                [
                    'slug'  => 'free_block_1_message_1_v1',
                    'message' => 'Learn how to configure Meta Conversion API with your PixelYourSite plugin.',
                    'title' => 'Meta Conversion API',
                    'button_text' => 'Watch video',
                    'button_url' => 'https://www.youtube.com/watch?v=uXTpgFu2V-E'
                ],
                [
                    'slug'  => 'free_block_1_message_2_v3',
                    'title' => 'Google Tag Manager Support',
                    'message' => 'NEW: Learn how to use GTM with PixelYourSite',
                    'button_text' => 'Watch video',
                    'button_url' => 'https://www.youtube.com/watch?v=qKJ3mmCgT3M',
                ],
                [
                    'slug'  => 'free_block_1_message_3_v2',
                    'title' => 'Meta EMQ Score',
                    'message' => 'What is EMQ and how you can improve it.',
                    'button_text' => 'Learn more',
                    'button_url' => 'https://www.pixelyoursite.com/facebook-event-match-quality-score',
                ]
            ],
            'optoutEnabel' => true,
            'optoutMessage' => "This is message 1 of a series of 3 notifications containing tips and tricks about how to use our plugin.",
            'optoutButtonText' => "Don't show me more tips"
        ],
        [
            'order' => '2',
            'wait' => 12,
            'type' => 'event chain',
            'location' => 'backend',
            'enabelYoutubeLink' => true,
            'enabelLogo' => true,
            'color' => 'green',
            'multiMessage' => [

                [
                  'slug'  => 'free_block_2_message_3_v2',
                  'message' => 'How to enable Google Consent Mode when using PixelYourSite.',
                  'title' => 'Google Consent Mode V2',
                  'button_text' => 'Watch video',
                  'button_url' => 'https://www.youtube.com/watch?v=uYfFesnKcW0',
                ],

                [
                    'slug'  => 'free_block_2_message_1_v1',
                    'message' => 'Learn how to create Custom Conversions on Meta using your pixel events. Use them to optimize your ads and track your ads results.',
                    'title' => 'Meta Custom Conversions using Events',
                    'button_text' => 'Watch video',
                    'button_url' => 'https://www.youtube.com/watch?v=w97FATUy7ok'
                ],
                [
                    'slug'  => 'free_block_2_message_2_v2',
                    'message' => 'Meta EMQ numbers can be misleading. Watch this video to see why, and what you can do to improve them.',
                    'title' => 'Meta EMQ Explained and How to Improve It!',
                    'button_text' => 'Watch video',
                    'button_url' => 'https://www.youtube.com/watch?v=oHoWyT8UQWo',
                ],



            ],
            'optoutEnabel' => true,
            'optoutMessage' => "This is message 2 of a series of 3 notifications containing tips and tricks about how to use our plugin.",
            'optoutButtonText' => "Don't show me more tips"
        ],


        [
            'order' => '3',
            'wait' => 24,
            'type' => 'event chain',
            'location' => 'backend',
            'enabelYoutubeLink' => true,
            'enabelLogo' => true,
            'multiMessage' => [
                [
                    'slug'  => 'free_block_3_message_1_v1',
                    'title' => 'Multiple Meta tags with CAPI support',
                    'message' => 'Learn how you can add multiple Meta (Facebook) tags with Conversion API support.',
                    'button_text' => 'Watch video',
                    'button_url' => 'https://www.youtube.com/watch?v=HM98mGZshvc',
                ],
                [
                    'slug'  => 'free_block_3_message_2_v1',
                    'title' => 'What WooCommerce products your ads sold',
                    'message' => 'Meta doesn"t show you what products your ads sold, but there is an easy way to find out.',
                    'button_text' => 'Watch video',
                    'button_url' => 'https://www.youtube.com/watch?v=b-eYdx9QK0Q',
                ],

                [
                    'slug'  => 'free_block_3_message_3_v1',
                    'title' => 'Medical Content - Meta Tracking',
                    'message' => 'Meta has restrictions for MEDICAL content. Learn how to adapt!',
                    'button_text' => 'Learn more',
                    'button_url' => 'https://www.pixelyoursite.com/medical-site-meta-tracking',
                ],
            ],
            'optoutEnabel' => true,
            'optoutMessage' => "This is message 3 (the last one) of a series of 3 notifications containing tips and tricks about how to use our plugin.",
            'optoutButtonText' => "Don't show me more tips"

        ],




        /*
            [
                'type' => 'promo',
                'location' => 'backend',
                'plugins' => [],
                'slug'  => 'wcf_and_woo_promo_v1',
                'enabelDismiss' => true,
                'message' => 'HOT: Improve CartFlows tracking with PixelYourSite Professional: <a target="_blank" href="https://www.youtube.com/watch?v=-rA3rxq812g">CLICK TO LEARN MORE</a>'
            ]

              */

    ];
}
