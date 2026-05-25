<?php
session_start();
require_once __DIR__ . "/validation.php";
require_once __DIR__ . "/config/audit_helper.php";

$val         = new Validation();
$message     = "";
$messageType = "";

// Clear old input if arriving fresh (not a failed submission redirect)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['retry'])) {
    unset($_SESSION['old']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $_SESSION['old'] = $_POST;
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: registration.php?retry=1");
        exit;
    }

    // Handle logo upload
    $logo_url = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $_SESSION['old']   = $_POST;
            $_SESSION['error'] = "Logo must be JPG, PNG, or WEBP.";
            header("Location: registration.php?retry=1");
            exit;
        }
        if ($_FILES['logo']['size'] > $maxSize) {
            $_SESSION['old']   = $_POST;
            $_SESSION['error'] = "Logo must be under 2MB.";
            header("Location: registration.php?retry=1");
exit;
        }

        $uploadDir = __DIR__ . '/dashboard/uploads/logos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
            $logo_url = 'dashboard/uploads/logos/' . $filename;
        }
    }

    $result = $val->register(
        $_POST['username'],
        $_POST['password'],
        $_POST['fullname'],
        $_POST['fastfood_name'],
        $_POST['email'],
        $_POST['phone_number']   ?? null,
        $_POST['business_type']  ?? null,
        $_POST['tin_number']     ?? null,
        $_POST['dti_sec_number'] ?? null,
        $_POST['business_permit']?? null,
        $_POST['address']        ?? null,
        $_POST['city']           ?? null,
        $_POST['province']       ?? null,
        $_POST['zip_code']       ?? null,
        $logo_url,
        $_POST['logo_shape'] ?? 'circle'
    );

    if ($result === true) {
        unset($_SESSION['old']);

        // ── Audit: owner account created ──
        try {
            $db   = new Database();
            $conn = $db->connect();
            $actor = $_SESSION ?? ['admin_id' => null, 'username' => 'registration'];
            audit_log($conn, $actor, 'owner_created', 'owner', null, $_POST['username'], "New owner registered: {$_POST['username']} ({$_POST['fastfood_name']})");
        } catch (Exception $e) {}

        $_SESSION['success'] = "Account created successfully! Please login.";
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['old']   = $_POST;
        $_SESSION['error'] = is_string($result) ? $result : "Registration failed. Please try again.";
        header("Location: registration.php?retry=1");
exit;
    }
}

if (isset($_SESSION['error'])) {
    $message     = $_SESSION['error'];
    $messageType = $_SESSION['error_type'] ?? "error";
    unset($_SESSION['error'], $_SESSION['error_type']);
}
if (isset($_SESSION['success'])) {
    $message     = $_SESSION['success'];
    $messageType = "success";
    unset($_SESSION['success']);
}

if (isset($_GET['clear_old'])) {
    unset($_SESSION['old']);
}

$old = $_SESSION['old'] ?? [];

/* =====================================================
   PHILIPPINES PROVINCES + CITIES DATA
   ===================================================== */
$ph_data = [
    "Abra" => ["Bangued","Boliney","Bucay","Bucloc","Daguioman","Danglas","Dolores","La Paz","Lagayan","Langiden","Licuan-Baay","Luba","Malibcong","Manabo","Peñarrubia","Pidigan","Pilar","Sallapadan","San Isidro","San Juan","San Quintin","Tayum","Tineg","Tubo","Villaviciosa"],
    "Agusan del Norte" => ["Butuan City","Cabadbaran City","Buenavista","Carmen","Jabonga","Kitcharao","Las Nieves","Magallanes","Nasipit","Remedios T. Romualdez","Santiago","Tubay"],
    "Agusan del Sur" => ["Bayugan City","Bunawan","Esperanza","La Paz","Loreto","Prosperidad","Rosario","San Francisco","San Luis","Santa Josefa","Sibagat","Talacogon","Trento","Veruela"],
    "Aklan" => ["Altavas","Balete","Banga","Batan","Buruanga","Ibajay","Kalibo","Lezo","Libacao","Madalag","Makato","Malay","Malinao","Nabas","New Washington","Numancia","Tangalan"],
    "Albay" => ["Legazpi City","Ligao City","Tabaco City","Bacacay","Camalig","Daraga","Guinobatan","Jovellar","Libon","Malilipot","Malinao","Manito","Oas","Pio Duran","Polangui","Rapu-Rapu","Santo Domingo","Tiwi"],
    "Antique" => ["San Jose de Buenavista","Anini-y","Barbaza","Belison","Bugasong","Caluya","Culasi","Hamtic","Laua-an","Libertad","Pandan","Patnongon","San Remigio","Sebaste","Sibalom","Tibiao","Tobias Fornier","Valderrama"],
    "Apayao" => ["Calanasan","Conner","Flora","Kabugao","Luna","Pudtol","Santa Marcela"],
    "Aurora" => ["Baler","Casiguran","Dilasag","Dinalungan","Dingalan","Dipaculao","Maria Aurora","San Luis"],
    "Basilan" => ["Isabela City","Akbar","Al-Barka","Hadji Mohammad Ajul","Hadji Muhtamad","Lamitan City","Lantawan","Maluso","Sumisip","Tabuan-Lasa","Tipo-Tipo","Tuburan","Ungkaya Pukan"],
    "Bataan" => ["Balanga City","Abucay","Bagac","Dinalupihan","Hermosa","Limay","Mariveles","Morong","Orani","Orion","Pilar","Samal"],
    "Batanes" => ["Basco","Itbayat","Ivana","Mahatao","Sabtang","Uyugan"],
    "Batangas" => ["Batangas City","Lipa City","Tanauan City","Agoncillo","Alitagtag","Balayan","Balete","Bauan","Calaca","Calatagan","Cuenca","Ibaan","Laurel","Lemery","Lian","Lobo","Mabini","Malvar","Mataas na Kahoy","Nasugbu","Padre Garcia","Rosario","San Jose","San Juan","San Luis","San Nicolas","San Pascual","Santa Teresita","Santo Tomas","Taal","Talisay","Taysan","Tingloy","Tuy"],
    "Benguet" => ["Baguio City","Atok","Bakun","Bokod","Buguias","Itogon","Kabayan","Kapangan","Kibungan","La Trinidad","Mankayan","Sablan","Tuba","Tublay"],
    "Biliran" => ["Naval","Almeria","Biliran","Cabucgayan","Caibiran","Culaba","Kawayan","Maripipi"],
    "Bohol" => ["Tagbilaran City","Alburquerque","Alicia","Anda","Antequera","Baclayon","Balilihan","Batuan","Bien Unido","Bilar","Buenavista","Calape","Candijay","Carmen","Catigbian","Clarin","Corella","Cortes","Dagohoy","Danao","Dauis","Dimiao","Duero","Garcia Hernandez","Guindulman","Inabanga","Jagna","Lila","Loay","Loboc","Loon","Mabini","Maribojoc","Panglao","Pilar","Pitogo","Sagbayan","San Isidro","San Miguel","Sevilla","Sierra Bullones","Sikatuna","Talibon","Trinidad","Tubigon","Ubay","Valencia"],
    "Bukidnon" => ["Malaybalay City","Valencia City","Baungon","Cabanglasan","Damulog","Dangcagan","Don Carlos","Impasugong","Kadingilan","Kalilangan","Kibawe","Kitaotao","Lantapan","Libona","Malitbog","Manolo Fortich","Maramag","Pangantucan","Quezon","San Fernando","Sumilao","Talakag"],
    "Bulacan" => ["Malolos City","Meycauayan City","San Jose del Monte City","Angat","Balagtas","Baliuag","Bocaue","Bulakan","Bustos","Calumpit","Doña Remedios Trinidad","Guiguinto","Hagonoy","Marilao","Norzagaray","Obando","Pandi","Paombong","Plaridel","Pulilan","San Ildefonso","San Miguel","San Rafael","Santa Maria"],
    "Cagayan" => ["Tuguegarao City","Abulug","Alcala","Allacapan","Amulung","Aparri","Baggao","Ballesteros","Buguey","Calayan","Camalaniugan","Claveria","Enrile","Gattaran","Gonzaga","Iguig","Lal-lo","Lasam","Pamplona","Peñablanca","Piat","Rizal","Sanchez-Mira","Santa Ana","Santa Praxedes","Santa Teresita","Santo Niño","Solana","Tuao"],
    "Camarines Norte" => ["Daet","Basud","Capalonga","Jose Panganiban","Labo","Mercedes","Paracale","San Lorenzo Ruiz","San Vicente","Santa Elena","Talisay","Vinzons"],
    "Camarines Sur" => ["Naga City","Iriga City","Baao","Balatan","Bato","Bombon","Buhi","Bula","Cabusao","Calabanga","Camaligan","Canaman","Caramoan","Del Gallego","Gainza","Garchitorena","Goa","Lagonoy","Libmanan","Lupi","Magarao","Milaor","Minalabac","Nabua","Ocampo","Pamplona","Pasacao","Pili","Presentacion","Ragay","Sagñay","San Fernando","San Jose","Sipocot","Siruma","Tigaon","Tinambac"],
    "Camiguin" => ["Mambajao","Catarman","Guinsiliban","Mahinog","Sagay"],
    "Capiz" => ["Roxas City","Cuartero","Dao","Dumalag","Dumarao","Ivisan","Jamindan","Ma-ayon","Mambusao","Panay","Panitan","Pilar","Pontevedra","President Roxas","Sapi-an","Sigma","Tapaz"],
    "Catanduanes" => ["Virac","Bagamanoc","Baras","Bato","Caramoran","Gigmoto","Pandan","Panganiban","San Andres","San Miguel","Viga"],
    "Cavite" => ["Cavite City","Bacoor City","Dasmariñas City","General Trias City","Imus City","Tagaytay City","Trece Martires City","Alfonso","Amadeo","Carmona","General Mariano Alvarez","Indang","Kawit","Magallanes","Maragondon","Mendez","Naic","Noveleta","Rosario","Silang","Tanza","Ternate"],
    "Cebu" => ["Cebu City","Lapu-Lapu City","Mandaue City","Bogo City","Carcar City","Danao City","Naga City","Talisay City","Toledo City","Alcantara","Alcoy","Alegria","Aloguinsan","Argao","Asturias","Badian","Balamban","Bantayan","Barili","Boljoon","Borbon","Compostela","Consolacion","Cordova","Daanbantayan","Dalaguete","Dumanjug","Ginatilan","Liloan","Madridejos","Malabuyoc","Medellin","Minglanilla","Moalboal","Oslob","Pilar","Pinamungajan","Poro","Ronda","Samboan","San Fernando","San Francisco","San Remigio","Santa Fe","Santander","Sibonga","Sogod","Tabogon","Tabuelan","Tuburan","Tudela"],
    "Compostela Valley" => ["Nabunturan","Compostela","Laak","Mabini","Maco","Maragusan","Mawab","Monkayo","Montevista","New Bataan","Pantukan"],
    "Cotabato" => ["Kidapawan City","Alamada","Aleosan","Antipas","Arakan","Banisilan","Carmen","Kabacan","Libungan","Magpet","Makilala","Matalam","Midsayap","M'lang","Pigcawayan","Pikit","President Roxas","Tulunan"],
    "Davao de Oro" => ["Nabunturan","Compostela","Laak","Mabini","Maco","Maragusan","Mawab","Monkayo","Montevista","New Bataan","Pantukan"],
    "Davao del Norte" => ["Tagum City","Panabo City","Samal City","Asuncion","Braulio E. Dujali","Carmen","Kapalong","New Corella","San Isidro","Santo Tomas","Talaingod"],
    "Davao del Sur" => ["Davao City","Digos City","Bansalan","Don Marcelino","Hagonoy","Jose Abad Santos","Kiblawan","Magsaysay","Malalag","Matanao","Padada","Santa Cruz","Sulop"],
    "Davao Occidental" => ["Malita","Don Marcelino","Jose Abad Santos","Santa Maria","Sarangani"],
    "Davao Oriental" => ["Mati City","Baganga","Banaybanay","Boston","Caraga","Cateel","Governor Generoso","Lupon","Manay","San Isidro","Tarragona"],
    "Dinagat Islands" => ["San Jose","Basilisa","Cagdianao","Dinagat","Libjo","Loreto","Tubajon"],
    "Eastern Samar" => ["Borongan City","Arteche","Balangiga","Balangkayan","Can-avid","Dolores","General MacArthur","Giporlos","Guiuan","Hernani","Jipapad","Lawaan","Llorente","Maslog","Maydolong","Mercedes","Oras","Quinapondan","Salcedo","San Julian","San Policarpo","Sulat","Taft"],
    "Guimaras" => ["Jordan","Buenavista","Nueva Valencia","San Lorenzo","Sibunag"],
    "Ifugao" => ["Lagawe","Aguinaldo","Alfonso Lista","Asipulo","Banaue","Hingyon","Hungduan","Kiangan","Lamut","Mayoyao","Tinoc"],
    "Ilocos Norte" => ["Laoag City","Batac City","Adams","Bacarra","Badoc","Bangui","Banna","Burgos","Carasi","Currimao","Dingras","Dumalneg","Marcos","Nueva Era","Pagudpud","Paoay","Pasuquin","Piddig","Pinili","San Nicolas","Sarrat","Solsona","Vintar"],
    "Ilocos Sur" => ["Vigan City","Bantay","Cabugao","Caoayan","Cervantes","Galimuyod","Gregorio del Pilar","Lidlidda","Magsingal","Nagbukel","Narvacan","Quirino","Salcedo","San Emilio","San Esteban","San Ildefonso","San Juan","San Vicente","Santa","Santa Catalina","Santa Cruz","Santa Lucia","Santa Maria","Santiago","Sigay","Sinait","Sugpon","Suyo","Tagudin"],
    "Iloilo" => ["Iloilo City","Passi City","Ajuy","Alimodian","Anilao","Badiangan","Balasan","Banate","Barotac Nuevo","Barotac Viejo","Batad","Bingawan","Cabatuan","Calinog","Carles","Concepcion","Dingle","Dueñas","Dumangas","Estancia","Guimbal","Igbaras","Janiuay","Lambunao","Leganes","Lemery","Leon","Maasin","Miagao","Mina","New Lucena","Oton","Pavia","Pototan","San Dionisio","San Enrique","San Joaquin","San Miguel","San Rafael","Santa Barbara","Sara","Tigbauan","Tubungan","Zarraga"],
    "Isabela" => ["Ilagan City","Cauayan City","Santiago City","Alicia","Angadanan","Aurora","Benito Soliven","Burgos","Cabagan","Cabatuan","Cordon","Delfin Albano","Dinapigue","Divilacan","Echague","Gamu","Jones","Luna","Maconacon","Mallig","Naguilian","Palanan","Quezon","Quirino","Ramon","Reina Mercedes","Roxas","San Agustin","San Guillermo","San Isidro","San Manuel","San Mariano","San Mateo","San Pablo","Santa Maria","Santo Tomas","Tumauini"],
    "Kalinga" => ["Tabuk City","Balbalan","Lubuagan","Pasil","Pinukpuk","Rizal","Tanudan","Tinglayan"],
    "La Union" => ["San Fernando City","Agoo","Aringay","Bacnotan","Bagulin","Balaoan","Bangar","Bauang","Burgos","Caba","Luna","Naguilian","Pugo","Rosario","San Gabriel","San Juan","Santo Tomas","Santol","Sudipen","Tubao"],
    "Laguna" => ["San Pablo City","Santa Rosa City","Calamba City","Biñan City","Cabuyao City","San Pedro City","Alaminos","Bay","Calauan","Cavinti","Famy","Kalayaan","Liliw","Los Baños","Luisiana","Lumban","Mabitac","Magdalena","Majayjay","Nagcarlan","Paete","Pagsanjan","Pakil","Pangil","Pila","Rizal","Santa Cruz","Santa Maria","Siniloan","Victoria"],
    "Lanao del Norte" => ["Iligan City","Bacolod","Baloi","Baroy","Kapatagan","Kauswagan","Kolambugan","Lala","Linamon","Magsaysay","Maigo","Matungao","Munai","Nunungan","Pantao Ragat","Pantar","Poona Piagapo","Salvador","Sapad","Sultan Naga Dimaporo","Tagoloan","Tangcal","Tubod"],
    "Lanao del Sur" => ["Marawi City","Bacolod-Kalawi","Balabagan","Balindong","Bayang","Binidayan","Buadiposo-Buntong","Bubong","Bumbaran","Butig","Calanogas","Ditsaan-Ramain","Ganassi","Kapai","Kapatagan","Lumba-Bayabao","Lumbaca-Unayan","Lumbatan","Lumbayanague","Madalum","Madamba","Maguing","Malabang","Marantao","Marogong","Masiu","Mulondo","Pagayawan","Piagapo","Picong","Poona Bayabao","Pualas","Saguiaran","Sultan Dumalondong","Sultan Gumander","Tagoloan II","Tamparan","Taraka","Tubaran","Tugaya","Wao"],
    "Leyte" => ["Tacloban City","Ormoc City","Baybay City","Abuyog","Alangalang","Albuera","Babatngon","Barugo","Bato","Burauen","Calubian","Capoocan","Carigara","Dagami","Dulag","Hilongos","Hindang","Inopacan","Isabel","Jaro","Javier","Julita","Kananga","La Paz","Leyte","Liloan","Macarthur","Mahaplag","Matag-ob","Matalom","Mayorga","Merida","Palo","Palompon","Pastrana","San Isidro","San Miguel","Santa Fe","Silvino Lobos","Tabango","Tabontabon","Tanauan","Tolosa","Tunga","Villaba"],
    "Maguindanao" => ["Cotabato City","Buldon","Buluan","Datu Abdullah Sangki","Datu Anggal Midtimbang","Datu Blah T. Sinsuat","Datu Hoffer Ampatuan","Datu Montawal","Datu Odin Sinsuat","Datu Paglas","Datu Piang","Datu Salibo","Datu Saudi-Ampatuan","Datu Unsay","General Salipada K. Pendatun","Guindulungan","Kabuntalan","Mangudadatu","Mamasapano","Pandag","Parang","Rajah Buayan","Shariff Aguak","Shariff Saydona Mustapha","South Upi","Sultan Kudarat","Sultan Mastura","Sultan sa Barongis","Talayan","Upi"],
    "Marinduque" => ["Boac","Buenavista","Gasan","Mogpog","Santa Cruz","Torrijos"],
    "Masbate" => ["Masbate City","Aroroy","Baleno","Balud","Batuan","Cataingan","Cawayan","Claveria","Dimasalang","Esperanza","Mandaon","Milagros","Mobo","Monreal","Palanas","Pio V. Corpuz","Placer","San Fernando","San Jacinto","San Pascual","Uson"],
    "Metro Manila" => ["Caloocan","Las Piñas","Makati","Malabon","Mandaluyong","Manila","Marikina","Muntinlupa","Navotas","Parañaque","Pasay","Pasig","Pateros","Quezon City","San Juan","Taguig","Valenzuela"],
    "Misamis Occidental" => ["Oroquieta City","Ozamiz City","Tangub City","Aloran","Baliangao","Bonifacio","Calamba","Clarin","Concepcion","Don Victoriano Chiongbian","Jimenez","Lopez Jaena","Panaon","Plaridel","Sapang Dalaga","Sinacaban","Tudela"],
    "Misamis Oriental" => ["Cagayan de Oro City","El Salvador City","Gingoog City","Alubijid","Balingasag","Balingoan","Binuangan","Claveria","Gitagum","Initao","Jasaan","Kinoguitan","Lagonglong","Laguindingan","Libertad","Lugait","Magsaysay","Manticao","Medina","Naawan","Opol","Salay","Sugbongcogon","Tagoloan","Talisayan","Villanueva"],
    "Mountain Province" => ["Bontoc","Barlig","Bauko","Besao","Natonin","Paracelis","Sabangan","Sadanga","Sagada","Tadian"],
    "Negros Occidental" => ["Bacolod City","Bago City","Cadiz City","Escalante City","Himamaylan City","Kabankalan City","La Carlota City","Sagay City","San Carlos City","Silay City","Sipalay City","Talisay City","Victorias City","Binalbagan","Calatrava","Candoni","Cauayan","Enrique B. Magalona","Hinigaran","Hinoba-an","Ilog","Isabela","La Castellana","Manapla","Moises Padilla","Murcia","Pontevedra","Pulupandan","Salvador Benedicto","San Enrique","Toboso","Valladolid"],
    "Negros Oriental" => ["Dumaguete City","Bais City","Bayawan City","Canlaon City","Guihulngan City","Tanjay City","Amlan","Ayungon","Bacong","Basay","Bindoy","Dauin","Jimalalud","La Libertad","Mabinay","Manjuyod","Pamplona","San Jose","Santa Catalina","Siaton","Sibulan","Tayasan","Valencia","Zamboanguita"],
    "Northern Samar" => ["Catarman","Allen","Biri","Bobon","Capul","Catubig","Gamay","Laoang","Lapinig","Las Navas","Lavezares","Lope de Vega","Mapanas","Mondragon","Palapag","Pambujan","Rosario","San Antonio","San Isidro","San Jose","San Roque","San Vicente","Silvino Lobos","Victoria"],
    "Nueva Ecija" => ["Cabanatuan City","Gapan City","Muñoz City","Palayan City","San Jose City","Aliaga","Bongabon","Cabiao","Carranglan","Cuyapo","Gabaldon","General Mamerto Natividad","General Tinio","Guimba","Jaen","Laur","Licab","Llanera","Lupao","Nampicuan","Pantabangan","Peñaranda","Quezon","Rizal","San Antonio","San Isidro","San Leonardo","Santa Rosa","Santo Domingo","Talavera","Talugtug","Zaragoza"],
    "Nueva Vizcaya" => ["Bayombong","Alfonso Castañeda","Ambaguio","Aritao","Bagabag","Bambang","Diadi","Dupax del Norte","Dupax del Sur","Kayapa","Kasibu","Quezon","Santa Fe","Solano","Villaverde"],
    "Occidental Mindoro" => ["Mamburao","Abra de Ilog","Calintaan","Looc","Lubang","Magsaysay","Paluan","Rizal","Sablayan","San Jose","Santa Cruz"],
    "Oriental Mindoro" => ["Calapan City","Baco","Bansud","Bongabong","Bulalacao","Gloria","Mansalay","Naujan","Pinamalayan","Pola","Puerto Galera","Roxas","San Teodoro","Socorro","Victoria"],
    "Palawan" => ["Puerto Princesa City","Aborlan","Agutaya","Araceli","Balabac","Bataraza","Brooke's Point","Busuanga","Cagayancillo","Coron","Culion","Cuyo","Dumaran","El Nido","Kalayaan","Linapacan","Magsaysay","Narra","Quezon","Rizal","Roxas","San Vicente","Sofronio Española","Taytay"],
    "Pampanga" => ["Angeles City","San Fernando City","Mabalacat City","Apalit","Arayat","Bacolor","Candaba","Floridablanca","Guagua","Lubao","Macabebe","Magalang","Masantol","Mexico","Minalin","Porac","San Luis","San Simon","Santa Ana","Santa Rita","Santo Tomas","Sasmuan"],
    "Pangasinan" => ["Dagupan City","San Carlos City","Urdaneta City","Alaminos City","Agno","Aguilar","Alcala","Anda","Asingan","Balungao","Bani","Basista","Bautista","Bayambang","Binalonan","Binmaley","Bolinao","Bugallon","Burgos","Calasiao","Dasol","Infanta","Labrador","Laoac","Lingayen","Mabini","Malasiqui","Manaoag","Mangaldan","Mangatarem","Mapandan","Natividad","Pozorrubio","Rosales","San Fabian","San Jacinto","San Manuel","San Nicolas","San Quintin","Santa Barbara","Santa Maria","Santo Tomas","Sison","Sual","Tayug","Umingan","Urbiztondo","Villasis"],
    "Quezon" => ["Lucena City","Tayabas City","Agdangan","Alabat","Atimonan","Buenavista","Burdeos","Calauag","Candelaria","Catanauan","Dolores","General Luna","General Nakar","Guinayangan","Gumaca","Infanta","Jomalig","Lopez","Lucban","Macalelon","Mauban","Mulanay","Padre Burgos","Pagbilao","Panukulan","Patnanungan","Perez","Pitogo","Plaridel","Polillo","Quezon","Real","Sampaloc","San Andres","San Antonio","San Francisco","San Narciso","Sariaya","Tagkawayan","Tiaong","Unisan"],
    "Quirino" => ["Cabarroguis","Aglipay","Diffun","Maddela","Nagtipunan","Saguday"],
    "Rizal" => ["Antipolo City","Angono","Baras","Binangonan","Cainta","Cardona","Jala-jala","Morong","Pililla","Rodriguez","San Mateo","Tanay","Taytay","Teresa"],
    "Romblon" => ["Romblon","Alcantara","Banton","Cajidiocan","Calatrava","Concepcion","Corcuera","Ferrol","Looc","Magdiwang","Odiongan","San Agustin","San Andres","San Fernando","San Jose","Santa Fe","Santa Maria"],
    "Samar" => ["Catbalogan City","Basey","Calbayog City","Calbiga","Daram","Gandara","Hinabangan","Jiabong","Langkawi","Marabut","Matuguinao","Motiong","Pagsanghan","Paranas","Pinabacdao","San Jorge","San Jose de Buan","San Sebastian","Santa Rita","Santo Niño","Tagapul-an","Talalora","Tarangnan","Villareal","Zumarraga"],
    "Sarangani" => ["Alabel","Glan","Kiamba","Maasim","Maitum","Malapatan","Malungon"],
    "Siquijor" => ["Siquijor","Enrique Villanueva","Larena","Lazi","Maria","San Juan"],
    "Sorsogon" => ["Sorsogon City","Barcelona","Bulan","Bulusan","Casiguran","Castilla","Donsol","Gubat","Irosin","Juban","Magallanes","Matnog","Pilar","Prieto Diaz","Santa Magdalena","Sta. Magdalena"],
    "South Cotabato" => ["Koronadal City","General Santos City","Banga","Lake Sebu","Norala","Polomolok","Santo Niño","Surallah","T'boli","Tantangan","Tupi"],
    "Southern Leyte" => ["Maasin City","Anahawan","Bontoc","Hinunangan","Hinundayan","Libagon","Liloan","Limasawa","Macrohon","Malitbog","Padre Burgos","Pintuyan","Saint Bernard","San Francisco","San Juan","San Ricardo","Silago","Sogod","St. Bernard","Tomas Oppus"],
    "Sultan Kudarat" => ["Isulan","Bagumbayan","Columbio","Esperanza","Kalamansig","Lambayong","Lebak","Lutayan","Palimbang","President Quirino","Sen. Ninoy Aquino","Tacurong City"],
    "Sulu" => ["Jolo","Hadji Panglima Tahil","Indanan","Kalingalan Caluang","Lugus","Luuk","Maimbung","Old Panamao","Omar","Pangutaran","Parang","Pata","Patikul","Siasi","Talipao","Tapul","Tongkil"],
    "Surigao del Norte" => ["Surigao City","Alegria","Bacuag","Burgos","Claver","Dapa","Del Carmen","General Luna","Gigaquit","Mainit","Malimono","Pilar","Placer","San Benito","San Francisco","San Isidro","Santa Monica","Sison","Socorro","Tagana-an","Tubod"],
    "Surigao del Sur" => ["Tandag City","Bislig City","Barobo","Bayabas","Cagwait","Cantilan","Carmen","Carrascal","Cortes","Hinatuan","Lanuza","Lianga","Lingig","Madrid","Marihatag","San Agustin","San Miguel","Tagbina","Tago"],
    "Tarlac" => ["Tarlac City","Anao","Bamban","Camiling","Capas","Concepcion","Gerona","La Paz","Mayantoc","Moncada","Paniqui","Pura","Ramos","San Clemente","San Jose","San Manuel","Santa Ignacia","Victoria"],
    "Tawi-Tawi" => ["Bongao","Languyan","Mapun","Panglima Sugala","Sapa-Sapa","Sibutu","Simunul","Sitangkai","South Ubian","Tandubas","Turtle Islands"],
    "Zambales" => ["Olongapo City","Botolan","Cabangan","Candelaria","Castillejos","Iba","Masinloc","Olongapo","Palauig","San Antonio","San Felipe","San Marcelino","San Narciso","Santa Cruz","Subic"],
    "Zamboanga del Norte" => ["Dipolog City","Dapitan City","Baliguian","Godod","Gutalac","Jose Dalman","Kalawit","Katipunan","La Libertad","Labason","Leon B. Postigo","Liloy","Manukan","Mutia","Piñan","Polanco","Pres. Manuel A. Roxas","Rizal","Salug","San Miguel","Sergio Osmeña Sr.","Siayan","Sibuco","Sibutad","Sindangan","Siocon","Sirawai","Tampilisan"],
    "Zamboanga del Sur" => ["Zamboanga City","Pagadian City","Aurora","Bayog","Dimataling","Dinas","Dumalinao","Dumingag","Guipos","Josefina","Kumalarang","Labangan","Lakewood","Lapuyan","Mahayag","Margosatubig","Midsalip","Molave","Pitogo","Ramon Magsaysay","San Miguel","San Pablo","Tabina","Tigbao","Tukuran","Vincenzo A. Sagun","Zamboanga City"],
    "Zamboanga Sibugay" => ["Ipil","Alicia","Buug","Diplahan","Imelda","Kabasalan","Mabuhay","Malangas","Naga","Olutanga","Payao","Roseller Lim","Siay","Talusan","Titay","Tungawan"],
];
ksort($ph_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register - iPOS</title>
    <link rel="stylesheet" href="design/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php include __DIR__ . '/dashboard/helpers/theme_loader.php'; ?>
    <style>
        .section-divider {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent, #be185d);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-top: 1.5px solid #f0d6e4;
            padding-top: 14px;
            margin: 18px 0 10px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .logo-upload-area {
            border: 2px dashed #e8d0da;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
            background: #fdf7f9;
            margin-bottom: 12px;
        }
        .logo-upload-area:hover { border-color: #be185d; }
        .logo-upload-icon { font-size: 28px; margin-bottom: 6px; color: #be185d; }
        .logo-upload-area p { font-size: 12px; color: #999; margin: 0; }
        .logo-upload-area small { font-size: 10px; color: #bbb; }

        .shape-btn {
            text-align: center;
            cursor: pointer;
            padding: 10px 16px;
            border: 2px solid #e8d0da;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            color: #999;
            transition: all 0.2s;
            min-width: 70px;
        }
        .shape-btn.active {
            border-color: #be185d;
            color: #be185d;
            background: #fdf0f5;
        }
        .shape-btn:hover {
            border-color: #be185d;
            color: #be185d;
        }

        .input-group { position: relative; }
        .select-caret {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 11px;
            color: #aaa;
        }
    </style>
</head>
<body>

<div class="reg-wrapper">

    <!-- LEFT PANEL -->
    <div class="reg-left">
        <div class="logo-box">
            <?php $logo_size = 42; $logo_show_text = true; include __DIR__ . '/dashboard/helpers/ipos_logo.php'; ?>
        </div>
        <div class="eyebrow-pill">+ Point of Sale</div>
        <h1>Start Managing<br>Your <span>Store Today</span></h1>
        <p>Join iPOS and take full control of your fast food business —
           from menu management to real-time order tracking, all in one place.</p>
        <div class="reg-steps">
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-burger"></i></div>
                <div class="step-text">
                    <strong>Build Your Menu</strong>
                    <span>Add items, set prices, and manage stock levels easily.</span>
                </div>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-bolt"></i></div>
                <div class="step-text">
                    <strong>Real-time Order Queue</strong>
                    <span>See incoming orders instantly and serve faster.</span>
                </div>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="step-text">
                    <strong>Track Your Sales</strong>
                    <span>Monitor income, top items, and daily performance.</span>
                </div>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="step-text">
                    <strong>Secure &amp; Private</strong>
                    <span>Your data is isolated — only you can see your store.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="reg-right">
        <div class="reg-card">

            <h3>Create Admin Account</h3>
            <span class="subtitle">Fastfood Owner Registration</span>

            <?php if (!empty($message)): ?>
    <div class="msg <?= $messageType ?>">
        <i class="fa-solid <?= $messageType === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php if ($messageType === 'error'): ?>
        <div class="msg" style="background:#fef9f0;border:1px solid #fcd34d;color:#92400e;font-size:12px;margin-top:-8px;">
            <i class="fa-solid fa-image" style="color:#f59e0b;"></i>
            Please re-select your logo — files cannot be kept after an error.
        </div>
    <?php endif; ?>
<?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <!-- BUSINESS LOGO -->
                <div class="section-divider"><i class="fa-solid fa-image"></i> Business Logo / Profile Picture</div>

                <div id="stepShapeLabel" style="font-size:12px;color:#999;text-align:center;margin-bottom:8px;">Step 1: Choose a shape for your logo</div>
                <div id="shapeChooser" style="display:flex;gap:10px;margin-bottom:12px;justify-content:center;">
                    <label class="shape-btn active" onclick="setShape('circle', this)">
                        <div style="width:40px;height:40px;background:var(--accent,#be185d);border-radius:50%;margin:0 auto 4px;"></div>
                        <span>Circle</span>
                        <input type="radio" name="logo_shape" value="circle" checked style="display:none;">
                    </label>
                    <label class="shape-btn" onclick="setShape('square', this)">
                        <div style="width:40px;height:40px;background:var(--accent,#be185d);border-radius:0;margin:0 auto 4px;"></div>
                        <span>Square</span>
                        <input type="radio" name="logo_shape" value="square" style="display:none;">
                    </label>
                    <label class="shape-btn" onclick="setShape('rounded', this)">
                        <div style="width:40px;height:40px;background:var(--accent,#be185d);border-radius:12px;margin:0 auto 4px;"></div>
                        <span>Rounded</span>
                        <input type="radio" name="logo_shape" value="rounded" style="display:none;">
                    </label>
                </div>

                <div id="stepUploadLabel" style="font-size:12px;color:#999;text-align:center;margin-bottom:8px;">Step 2: Upload your logo</div>
                <div id="logoUploadArea" class="logo-upload-area" onclick="document.getElementById('logoInput').click()">
                    <div id="logoPlaceholder">
                        <div class="logo-upload-icon"><i class="fa-solid fa-camera"></i></div>
                        <p>Click to upload your business logo</p>
                        <small>JPG, PNG, WEBP · Max 2MB · Recommended 500×500px</small>
                    </div>
                </div>

                <div id="logoPreviewWrap" style="display:none;text-align:center;margin-bottom:12px;">
                    <img id="logoPreview" style="width:120px;height:120px;object-fit:cover;border:3px solid #be185d;transition:border-radius 0.3s;display:block;margin:0 auto 8px;" alt="Logo Preview">
                    <button type="button" onclick="changeLogo()" style="margin-top:8px;font-size:11px;color:#be185d;background:none;border:none;cursor:pointer;text-decoration:underline;">
                        <i class="fa-solid fa-rotate"></i> Change Logo
                    </button>
                </div>

                <input type="file" id="logoInput" name="logo" accept="image/*"
                       style="display:none;" onchange="previewLogo(this)">
                <canvas id="logoCanvas" style="display:none;"></canvas>

                <!-- OWNER INFORMATION -->
                <div class="section-divider"><i class="fa-solid fa-user"></i> Owner Information</div>
                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="fullname" placeholder="Full Name"
                                   value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                                   autocomplete="name" required>
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-phone"></i></span>
                            <input type="text" name="phone_number" placeholder="Contact Number"
                                   value="<?= htmlspecialchars($old['phone_number'] ?? '') ?>"
                                   autocomplete="tel" maxlength="11" inputmode="numeric"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
                        </div>
                    </div>
                </div>
                <div class="input-group">
                    <span><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" placeholder="Email Address"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>

                <!-- BUSINESS INFORMATION -->
                <div class="section-divider"><i class="fa-solid fa-burger"></i> Business Information</div>
                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-store"></i></span>
                            <input type="text" name="fastfood_name" placeholder="Business Name"
                                   value="<?= htmlspecialchars($old['fastfood_name'] ?? '') ?>"
                                   autocomplete="organization" required>
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-shop"></i></span>
                            <select name="business_type" style="border:none;background:none;width:100%;outline:none;font-size:13px;color:var(--text-primary);">
                                <option value="">-- Select Business Type --</option>
                                <?php foreach(['Fast Food'] as $type): ?>
                                    <option value="<?= $type ?>" <?= ($old['business_type'] ?? '') === $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-id-card"></i></span>
                            <input type="text" name="tin_number" id="tin_number"
                                   placeholder="BIR TIN Number"
                                   value="<?= htmlspecialchars($old['tin_number'] ?? '') ?>"
                                   maxlength="15">
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-file-lines"></i></span>
                            <input type="text" name="dti_sec_number"
                                   placeholder="DTI/SEC Reg. Number"
                                   value="<?= htmlspecialchars($old['dti_sec_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="input-group">
                    <span><i class="fa-solid fa-scroll"></i></span>
                    <input type="text" name="business_permit"
                           placeholder="Business Permit Number (e.g. BP-2024-001234)"
                           value="<?= htmlspecialchars($old['business_permit'] ?? '') ?>">
                </div>

                <!-- BUSINESS LOCATION -->
                <div class="section-divider"><i class="fa-solid fa-location-dot"></i> Business Location</div>
                <div class="input-group">
                    <span><i class="fa-solid fa-house"></i></span>
                    <input type="text" name="address" placeholder="Complete Address (Street, Barangay)"
                           value="<?= htmlspecialchars($old['address'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-map"></i></span>
                            <select name="province" id="province" required>
                                <option value="">Select Province</option>
                                <?php foreach(array_keys($ph_data) as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>"
                                        <?= ($old['province'] ?? '') === $prov ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="select-caret">▾</span>
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-city"></i></span>
                            <select name="city" id="city" required>
                                <option value="">Select City / Municipality</option>
                                <?php
                                $oldProv = $old['province'] ?? '';
                                $oldCity = $old['city'] ?? '';
                                if ($oldProv && isset($ph_data[$oldProv])) {
                                    foreach ($ph_data[$oldProv] as $c) {
                                        $sel = ($oldCity === $c) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($c) . '" ' . $sel . '>' . htmlspecialchars($c) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <span class="select-caret">▾</span>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <span><i class="fa-solid fa-envelope-open-text"></i></span>
                    <input type="text" name="zip_code" id="zip_code"
                           placeholder="ZIP Code (4 digits)"
                           value="<?= htmlspecialchars($old['zip_code'] ?? '') ?>"
                           maxlength="4" inputmode="numeric" pattern="\d{4}">
                </div>

                <!-- ACCOUNT CREDENTIALS -->
                <div class="section-divider"><i class="fa-solid fa-key"></i> Account Credentials</div>
                <div class="input-group">
                    <span><i class="fa-solid fa-tag"></i></span>
                    <input type="text" name="username" placeholder="Username (4–20 characters)"
                           value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>
                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" id="password"
                                   placeholder="Password" autocomplete="new-password" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePassword('password')"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   placeholder="Confirm Password" autocomplete="new-password" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePassword('confirm_password')"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <button type="submit" style="margin-top:20px;">Create Account</button>

            </form>

            <a href="login.php?clear_old=1" class="bottom-link">
                Already have an account? <span>Sign in</span>
            </a>

        </div>
    </div>

</div>

<!-- Philippines City Data (for dynamic city dropdown) -->
<script>
const PH_DATA = <?= json_encode($ph_data, JSON_UNESCAPED_UNICODE) ?>;

/* Province → City dropdown */
document.getElementById('province').addEventListener('change', function () {
    const citySelect = document.getElementById('city');
    const province   = this.value;
    citySelect.innerHTML = '<option value="">Select City / Municipality</option>';
    if (province && PH_DATA[province]) {
        PH_DATA[province].forEach(function (city) {
            const opt       = document.createElement('option');
            opt.value       = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
        });
    }
});

/* ZIP code — digits only */
document.getElementById('zip_code').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});

/* Auto-format TIN: 000-000-000 */
document.getElementById('tin_number').addEventListener('input', function () {
    let v = this.value.replace(/[^\d]/g, '');
    if (v.length > 9)      v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6,9) + '-' + v.slice(9,14);
    else if (v.length > 6) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
    else if (v.length > 3) v = v.slice(0,3) + '-' + v.slice(3);
    this.value = v;
});

/* Password toggle */
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const btn   = input.nextElementSibling;
    input.type  = input.type === 'password' ? 'text' : 'password';
    btn.innerHTML = input.type === 'password'
        ? '<i class="fa-solid fa-eye"></i>'
        : '<i class="fa-solid fa-eye-slash"></i>';
}

/* Logo shape */
let currentShape = 'circle';

function setShape(shape, el) {
    currentShape = shape;
    document.querySelectorAll('.shape-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input[type="radio"]').checked = true;
    if (document.getElementById('logoPreviewWrap').style.display !== 'none') {
        applyShapeToPreview(shape);
    }
}

function applyShapeToPreview(shape) {
    const preview = document.getElementById('logoPreview');
    if (shape === 'circle')  preview.style.borderRadius = '50%';
    if (shape === 'square')  preview.style.borderRadius = '0%';
    if (shape === 'rounded') preview.style.borderRadius = '16px';
}

function previewLogo(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas  = document.getElementById('logoCanvas');
            const size    = 300;
            canvas.width  = size;
            canvas.height = size;
            const ctx     = canvas.getContext('2d');
            const minSide = Math.min(img.width, img.height);
            const sx      = (img.width  - minSide) / 2;
            const sy      = (img.height - minSide) / 2;
            ctx.clearRect(0, 0, size, size);
            ctx.drawImage(img, sx, sy, minSide, minSide, 0, 0, size, size);

            const preview = document.getElementById('logoPreview');
            preview.src   = canvas.toDataURL('image/png');
            applyShapeToPreview(currentShape);

            document.getElementById('logoUploadArea').style.display  = 'none';
            document.getElementById('shapeChooser').style.display    = 'none';
            document.getElementById('stepShapeLabel').style.display  = 'none';
            document.getElementById('stepUploadLabel').style.display = 'none';
            document.getElementById('logoPreviewWrap').style.display = 'block';
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function changeLogo() {
    document.getElementById('logoUploadArea').style.display  = 'block';
    document.getElementById('shapeChooser').style.display    = 'flex';
    document.getElementById('stepShapeLabel').style.display  = 'block';
    document.getElementById('stepUploadLabel').style.display = 'block';
    document.getElementById('logoPreviewWrap').style.display = 'none';
    document.getElementById('logoInput').value               = '';
}
</script>

</body>
</html>