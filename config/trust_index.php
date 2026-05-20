<?php

return [
    'schema_version' => '1.0.0',

    'incident_categories' => [
        'MALWARE_RAT' => [
            'label' => 'Malware / RAT',
        ],
        'TRADE_SCAM' => [
            'label' => 'Trade Scam',
        ],
        'ACCOUNT_THEFT' => [
            'label' => 'Account Theft',
        ],
        'COOP_ABUSE' => [
            'label' => 'Co-op Abuse',
        ],
        'MARKET_MANIPULATION' => [
            'label' => 'Market Manipulation',
        ],
        'TRUST_TRADE' => [
            'label' => 'Trust Trade',
        ],
    ],

    'scammers' => [
        [
            'minecraft_username' => 'LocalDev',
            'player_uuid' => '00000000-0000-4000-8000-000000localdev',
            'aliases' => [],
            'listed_since' => '2026-05-19',
            'severity_level' => 'CRITICAL',
            'risk_score' => 100,
            'status' => 'CONFIRMED',
            'summary' => 'Test entry for development. Listed for distributing infected files and trade scam patterns in community Discords.',
            'reports' => [
                [
                    'report_id' => 'RPT-LOCALDEV-001',
                    'category' => 'MALWARE_RAT',
                    'date_reported' => '2026-05-19T09:15:00Z',
                    'description' => 'Shared infected runOptifine.bat in a Discord server, claiming it was a mod performance test.',
                    'items_involved' => ['N/A'],
                    'evidence' => [
                        'video_proofs' => [],
                        'chat_logs' => [],
                    ],
                ],
                [
                    'report_id' => 'RPT-LOCALDEV-002',
                    'category' => 'TRUST_TRADE',
                    'date_reported' => '2026-05-18T14:00:00Z',
                    'description' => 'Promised a valuable item after a trust trade, then logged off without completing the trade.',
                    'items_involved' => ['Coins', 'Trust trade'],
                    'evidence' => [
                        'video_proofs' => [],
                        'chat_logs' => [],
                    ],
                ],
            ],
        ],
    ],

    'scams' => [
        [
            'id' => 'collat',
            'number' => '1',
            'title' => 'Collat Scam',
            'description' => 'Not giving collateral or providing wrong collateral in a trade.',
            'group' => 'trade',
        ],
        [
            'id' => 'itemswap',
            'number' => '2.1',
            'title' => 'Itemswap Scam',
            'description' => 'Swapping an item for a less valuable one during a trade window.',
            'group' => 'trade',
        ],
        [
            'id' => 'reforge',
            'number' => '2.2',
            'title' => 'Reforge Scam',
            'description' => 'Claiming you need an item reforged when requirements are not met, then swapping it for a less valuable item.',
            'group' => 'trade',
        ],
        [
            'id' => 'crafting',
            'number' => '2.3',
            'title' => 'Crafting Scam',
            'description' => 'Same as reforge scams, but the scammer swaps crafting ingredients (e.g. Juju Shortbow).',
            'group' => 'trade',
        ],
        [
            'id' => 'coop',
            'number' => '3',
            'title' => 'Coop Scam',
            'description' => 'Being invited to a co-op and having everything taken from the profile.',
            'group' => 'profile',
        ],
        [
            'id' => 'loan',
            'number' => '4',
            'title' => 'Loan Scam',
            'description' => 'In-game friends borrowing items or coins and never returning them.',
            'group' => 'social',
        ],
        [
            'id' => 'claim-gift',
            'number' => '5',
            'title' => 'Claim-Gift Scam',
            'description' => 'Telling you that you won something and must claim it via a malicious link.',
            'group' => 'phishing',
        ],
        [
            'id' => 'fake-mod',
            'number' => '6',
            'title' => 'Fake Mod Scam',
            'description' => 'Distributing a RAT disguised as a mod to gain access to your PC.',
            'group' => 'security',
        ],
        [
            'id' => 'fake-verification',
            'number' => '7',
            'title' => 'Fake Verification',
            'description' => 'A fake verify bot in Discord that steals your Microsoft account.',
            'group' => 'security',
        ],
        [
            'id' => 'market-manipulation',
            'number' => '7.1',
            'title' => 'Market Manipulation Scam',
            'description' => 'Selling manipulated items to lowballers for huge profit (e.g. Phoenix Pet).',
            'group' => 'market',
        ],
        [
            'id' => 'market-manipulation-bz',
            'number' => '7.2',
            'title' => 'Market Manipulation Scam (BZ)',
            'description' => 'Manipulating Bazaar prices so insta-buy becomes expensive, then selling stock for profit.',
            'group' => 'market',
        ],
        [
            'id' => 'helping',
            'number' => '8',
            'title' => 'Helping Scam',
            'description' => 'Scammer offers to star your armor, then keeps it instead of returning it.',
            'group' => 'trade',
        ],
        [
            'id' => 'trust-trades',
            'number' => '9',
            'title' => 'Trust Trades (Drop Trades)',
            'description' => 'Promises a valuable item if you trade coins or items first, then gives nothing.',
            'group' => 'trade',
        ],
        [
            'id' => 'auction',
            'number' => '10',
            'title' => 'Auction Scam',
            'description' => 'Claims auction winners receive a valuable item, but the reward is worthless or missing.',
            'group' => 'market',
        ],
    ],
];
