<?php

// Türkmen dili — maşyn terjimesi. Düzedişleri Sargyt ediji sanaw görnüşinde berýär (TZ 3.4).
return [
    'saved' => 'Ýatda saklandy',
    'deleted' => 'Pozuldy',

    'errors' => [
        'forbidden' => 'Bu hereket üçin hukugyňyz ýok',
    ],

    'auth' => [
        'failed' => 'Login ýa-da parol nädogry',
        'blocked' => 'Işgär petiklenen',
        'locked' => 'Synanyşyk köp boldy. :minutes minutdan soň gaýtalaň.',
        'pin_left' => 'PIN nädogry. Galan synanyşyk: :left',
    ],

    'tables' => [
        'busy' => 'Stolda açyk sargyt bar — ilki ony ýapyň',
        'qr_regenerated' => 'Täze QR-kod döredildi, köne kod indi işlemeýär',
    ],

    'stop' => [
        'added' => 'Stop-sanawa goşuldy',
        'removed' => 'Stop-sanawdan aýryldy',
    ],

    'order' => [
        'dish_unavailable' => 'Nahar elýeterli däl ýa-da stop-sanawda',
        'only_draft' => 'Diňe iberilmedik pozisiýany üýtgedip bolýar',
        'remove_forbidden' => 'Aşhana iberilen nahary diňe «Aşhanadan soň pozmak» hukugy bolan işgär pozup biler',
        'reason_required' => 'Pozmagyň sebäbini görkeziň',
        'nothing_to_send' => 'Aşhana üçin täze pozisiýa ýok',
        'batch_not_found' => 'Pozisiýalar eýýäm işlenildi',
        'bad_waiter' => 'Bu işgäre stoly berip bolmaýar',
        'not_your_table' => 'Bu başga ofisiantyň stoly',
        'closed' => 'Sargyt eýýäm ýapyldy',
        'sent' => 'Aşhana iberildi',
        'rejected' => 'QR-sargyt ret edildi',
        'bill_requested' => 'Hasap soraldy — kassa stoly görýär',
        'transferred' => 'Stol berildi',
        'table_unavailable' => 'Bu stola geçirip bolmaýar',
        'table_busy' => 'Bu stolda eýýäm açyk sargyt bar',
        'moved' => 'Sargyt başga stola geçirildi',
    ],

    'guest' => [
        'cart_changed' => 'Siz saýlaýarkaňyz menýu täzelendi. Sargydy barlaň.',
    ],

    'cashier' => [
        'not_active' => 'Hasap eýýäm ýapyldy',
        'unsent_items' => 'Sargytda iberilmedik pozisiýalar bar — iberiň ýa-da pozuň',
        'empty' => 'Hasapda pozisiýa ýok',
        'discount_limit' => 'Siziň birwagtlaýyn arzanladyş çägiňiz — :k %',
        'mixed_invalid' => 'Nagt mukdar noldan uly we hasabyň jeminden kiçi bolmaly',
        'already_cancelled' => 'Töleg eýýäm ýatyryldy',
        'paid' => 'Töleg kabul edildi',
        'cancelled' => 'Töleg ýatyryldy',
        'tip_invalid' => 'Çaý pulunyň mukdary nädogry',
    ],

    'staff' => [
        'cannot_block_self' => 'Özüňizi petikläp bilmeýärsiňiz',
        'role_system' => 'TZ-daky rollary pozup bolmaýar',
        'role_has_users' => 'Bu rolda işgärler bar',
        'owner_protected' => 'Eýesiniň maglumatlaryny diňe Eýesiniň özi üýtgedýär',
        'blocked' => 'Işgär petiklendi',
        'unblocked' => 'Işgär açyldy',
    ],

    'reviews' => [
        'already' => 'Bu hasap eýýäm bahalandyryldy. Sag boluň!',
    ],

    'reports' => [
        'revenue' => 'Girdeji',
        'load' => 'Zalyň ýüki',
        'dishes' => 'Naharlar',
        'staff' => 'Işgärler',
        'date' => 'Sene',
        'bills' => 'Hasaplar',
        'avg_check' => 'Ortaça hasap',
        'cash' => 'Nagt',
        'card' => 'Kart',
        'tips' => 'Çaý puly',
        'prev_revenue' => 'Öňki döwür',
        'total' => 'Jemi',
        'weekday' => 'Gün',
        'weekdays' => ['Duşenbe', 'Sişenbe', 'Çarşenbe', 'Penşenbe', 'Anna', 'Şenbe', 'Ýekşenbe'],
        'top_dishes' => 'Iň köp satylan',
        'rare_dishes' => 'Iň seýrek',
        'dish' => 'Nahar',
        'qty' => 'Sany',
        'amount' => 'Jemi',
        'waiter' => 'Ofisiant',
        'avg_rating' => 'Ortaça baha',
        'reviews' => 'Teswirler',
        'removed' => 'Pozulan naharlar',
    ],

    'hall' => [
        'saved' => 'Zalyň shemasy ýatda saklandy',
        'busy_delete' => ':n stolda açyk sargyt bar — ony pozup bolmaýar',
        'too_small' => 'Täze ölçeg shemadaky stollary we obýektleri sygdyrmaýar',
        'not_empty' => 'Zalda stollar bar — ilki olary aýyryň',
        'out_of_bounds' => ':n stol zalyň çäginden çykýar',
        'element_out_of_bounds' => 'Obýekt zalyň çäginden çykýar',
        'overlap' => ':a we :b stollar biri-birine düşýär',
        'number_taken' => ':n belgili stol eýýäm bar',
        'foreign_table' => 'Bu stol başga zala degişli',
        'no_space' => 'Zalda boş ýer ýok — shemany giňeldiň ýa-da başga zal saýlaň',
    ],

    'guests' => [
        'title' => 'Myhmanlar',
        'unnamed' => 'Myhman',
        'phone_invalid' => 'Telefon belgisi +993 XX XXXXXX görnüşinde bolmaly',
        'phone_taken' => 'Bu belgili myhman eýýäm bar',
        'name' => 'Ady',
        'phone' => 'Telefon',
        'birthday' => 'Doglan güni',
        'address' => 'Eltip bermek salgysy',
        'comment' => 'Bellik',
        'visits' => 'Gelşi',
        'spent' => 'Satyn alyş jemi',
        'last_visit' => 'Soňky gelşi',
        'since' => 'Bazada şundan bäri',
    ],

    'bookings' => [
        'created' => 'Bron döredildi',
        'status_saved' => 'Bronuň ýagdaýy üýtgedildi',
        'not_editable' => 'Bu brony indi üýtgedip bolmaýar',
        'bad_transition' => 'Bronuň ýagdaýyny beýle üýtgedip bolmaýar',
        'no_table' => 'Ilki bron üçin stol belläň',
        'in_past' => 'Geçen wagta bronlap bolmaýar',
        'table_taken' => ':n stol bu wagtda eýýäm bronlanan',
    ],
];
