<?php

/*
| Business Lead Finder settings.
| Everything here is free: search links are built locally, no paid APIs are called.
*/

return [

    'state' => 'Tamil Nadu',

    'quantities' => [20, 50, 100],

    // Optional website checker (POST /api/business-finder/verify-website)
    'website_check' => [
        'user_agent' => env('BUSINESS_FINDER_USER_AGENT', 'SlingCRM-WebsiteCheck/1.0 (manual lead verification; one request per check)'),
        'connect_timeout' => 5,      // seconds
        'timeout' => 10,             // seconds
        'max_redirects' => 5,
        'cache_minutes' => 10,       // repeat checks of the same URL within this window return the cached result
        'robots_cache_minutes' => 60,
    ],

    // PDF export is rendered in memory, so it is capped.
    'pdf_max_rows' => 1000,

    // Districts of Tamil Nadu with their main towns (used by GET /api/business-finder/locations).
    'districts' => [
        'Ariyalur' => ['Ariyalur', 'Jayankondam', 'Sendurai', 'Udayarpalayam'],
        'Chengalpattu' => ['Chengalpattu', 'Tambaram', 'Pallavaram', 'Guduvanchery', 'Maraimalai Nagar', 'Madurantakam', 'Kelambakkam'],
        'Chennai' => ['Chennai', 'T. Nagar', 'Anna Nagar', 'Adyar', 'Velachery', 'Mylapore', 'Guindy', 'Ambattur', 'Porur', 'Egmore', 'Kodambakkam'],
        'Coimbatore' => ['Coimbatore', 'Pollachi', 'Mettupalayam', 'Sulur', 'Valparai', 'Gandhipuram', 'RS Puram', 'Peelamedu'],
        'Cuddalore' => ['Cuddalore', 'Chidambaram', 'Neyveli', 'Virudhachalam', 'Panruti'],
        'Dharmapuri' => ['Dharmapuri', 'Harur', 'Palacode', 'Pennagaram'],
        'Dindigul' => ['Dindigul', 'Palani', 'Kodaikanal', 'Oddanchatram', 'Vedasandur'],
        'Erode' => ['Erode', 'Gobichettipalayam', 'Bhavani', 'Sathyamangalam', 'Perundurai'],
        'Kallakurichi' => ['Kallakurichi', 'Sankarapuram', 'Ulundurpet', 'Chinnasalem'],
        'Kanchipuram' => ['Kanchipuram', 'Sriperumbudur', 'Walajabad', 'Uthiramerur'],
        'Kanniyakumari' => ['Nagercoil', 'Marthandam', 'Kanniyakumari', 'Colachel', 'Thuckalay'],
        'Karur' => ['Karur', 'Kulithalai', 'Aravakurichi', 'Krishnarayapuram'],
        'Krishnagiri' => ['Krishnagiri', 'Hosur', 'Denkanikottai', 'Pochampalli', 'Uthangarai'],
        'Madurai' => ['Madurai', 'Melur', 'Thirumangalam', 'Usilampatti', 'Vadipatti', 'Anna Nagar Madurai', 'K.K. Nagar Madurai'],
        'Mayiladuthurai' => ['Mayiladuthurai', 'Sirkazhi', 'Kuthalam', 'Tharangambadi'],
        'Nagapattinam' => ['Nagapattinam', 'Vedaranyam', 'Kilvelur', 'Thirukkuvalai'],
        'Namakkal' => ['Namakkal', 'Tiruchengode', 'Rasipuram', 'Kumarapalayam', 'Paramathi Velur'],
        'Nilgiris' => ['Ooty', 'Coonoor', 'Kotagiri', 'Gudalur'],
        'Perambalur' => ['Perambalur', 'Veppanthattai', 'Kunnam', 'Alathur'],
        'Pudukkottai' => ['Pudukkottai', 'Aranthangi', 'Alangudi', 'Illuppur', 'Karambakudi'],
        'Ramanathapuram' => ['Ramanathapuram', 'Paramakudi', 'Rameswaram', 'Kamuthi', 'Mudukulathur'],
        'Ranipet' => ['Ranipet', 'Arcot', 'Walajapet', 'Arakkonam', 'Sholinghur'],
        'Salem' => ['Salem', 'Attur', 'Mettur', 'Omalur', 'Edappadi', 'Yercaud'],
        'Sivaganga' => ['Sivaganga', 'Karaikudi', 'Devakottai', 'Manamadurai', 'Thiruppuvanam'],
        'Tenkasi' => ['Tenkasi', 'Sankarankovil', 'Kadayanallur', 'Puliyangudi', 'Courtallam'],
        'Thanjavur' => ['Thanjavur', 'Kumbakonam', 'Pattukkottai', 'Papanasam', 'Orathanadu'],
        'Theni' => ['Theni', 'Periyakulam', 'Bodinayakanur', 'Cumbum', 'Andipatti'],
        'Thoothukudi' => ['Thoothukudi', 'Kovilpatti', 'Tiruchendur', 'Kayalpattinam', 'Sathankulam'],
        'Tiruchirappalli' => ['Tiruchirappalli', 'Srirangam', 'Manapparai', 'Lalgudi', 'Thuraiyur', 'Musiri'],
        'Tirunelveli' => ['Tirunelveli', 'Palayamkottai', 'Ambasamudram', 'Valliyur', 'Cheranmahadevi'],
        'Tirupathur' => ['Tirupathur', 'Vaniyambadi', 'Ambur', 'Jolarpettai'],
        'Tiruppur' => ['Tiruppur', 'Avinashi', 'Palladam', 'Udumalaipettai', 'Dharapuram', 'Kangeyam'],
        'Tiruvallur' => ['Tiruvallur', 'Avadi', 'Ponneri', 'Gummidipoondi', 'Tiruttani', 'Poonamallee'],
        'Tiruvannamalai' => ['Tiruvannamalai', 'Arani', 'Cheyyar', 'Vandavasi', 'Polur'],
        'Tiruvarur' => ['Tiruvarur', 'Mannargudi', 'Thiruthuraipoondi', 'Nannilam', 'Kodavasal'],
        'Vellore' => ['Vellore', 'Katpadi', 'Gudiyatham', 'Pernambut'],
        'Viluppuram' => ['Viluppuram', 'Tindivanam', 'Gingee', 'Vanur', 'Kottakuppam'],
        'Virudhunagar' => ['Virudhunagar', 'Sivakasi', 'Rajapalayam', 'Aruppukottai', 'Srivilliputhur', 'Sattur'],
    ],
];
