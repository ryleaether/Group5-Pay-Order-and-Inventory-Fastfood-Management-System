<?php
session_start();
require_once __DIR__ . "/validation.php";

$val         = new Validation();
$message     = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $_SESSION['old'] = $_POST;
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: registration.php");
        exit;
    }

    // Handle logo upload
    error_log("Upload dir: " . __DIR__ . '/dashboard/uploads/logos/');
        error_log("Files array: " . print_r($_FILES, true));
    $logo_url = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $_SESSION['old']   = $_POST;
            $_SESSION['error'] = "Logo must be JPG, PNG, or WEBP.";
            header("Location: registration.php");
            exit;
        }
        if ($_FILES['logo']['size'] > $maxSize) {
            $_SESSION['old']   = $_POST;
            $_SESSION['error'] = "Logo must be under 2MB.";
            header("Location: registration.php");
            exit;
        }

       $uploadDir = __DIR__ . '/dashboard/uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
        $fullPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $fullPath)) {
            $logo_url = 'dashboard/uploads/logos/' . $filename;
        } else {
            // Log the error for debugging
            error_log("Logo upload failed. Tmp: " . $_FILES['logo']['tmp_name'] . " | Dest: " . $fullPath);
        }
    }

    error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        $_SESSION['success'] = "Account created successfully! Please login.";
        header("Location: login.php");
        exit;
    } else {
    $_SESSION['old']   = $_POST;
    $_SESSION['error'] = is_string($result) ? $result : "Registration failed. Please try again.";
    header("Location: registration.php");
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
    "Bohol" => ["Tagbilaran City","Alburquerque","Alicia","Anda","Antequera","Baclayon","Balilihan","Batuan","Bien Unido","Bilar","Buenavista","Calape","Candijay","Carmen","Catigbian","Clarin","Corella","Cortes","Dagohoy","Danao","Dauis","Dimiao","Duero","Garcia Hernandez","Guindulman","Inabanga","Jagna","Lila","Loay","Loboc","Loon","Mabini","Maribojoc","Panglao","Pilar","Pitogo","Presidency","Sagbayan","San Isidro","San Miguel","Sevilla","Sierra Bullones","Sikatuna","Talibon","Trinidad","Tubigon","Ubay","Valencia"],
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="design/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?php include __DIR__ . '/dashboard/helpers/theme_loader.php'; ?>
    <style>
        /* ── Section divider ── */
        .section-divider {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent-deep, #A33757);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-top: 1.5px solid #f0d6e4;
            padding-top: 14px;
            margin: 18px 0 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-divider i {
            font-size: 12px;
            color: var(--accent-deep, #A33757);
        }

        /* ── Logo upload area ── */
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
        .logo-upload-icon { font-size: 28px; margin-bottom: 6px; color: #c4a0b0; }
        .logo-upload-area p { font-size: 12px; color: #999; margin: 0; }
        .logo-upload-area small { font-size: 10px; color: #bbb; }

        /* ── Shape buttons ── */
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

        /* ── Validation states ── */
        .field-error {
            font-size: 11px;
            color: #dc2626;
            margin-top: 4px;
            margin-bottom: 0;
            display: none;
            padding-left: 2px;
        }
        .input-group.is-invalid {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 2px rgba(220,38,38,0.15) !important;
        }
        .input-group.is-valid {
            border-color: #16a34a !important;
            box-shadow: 0 0 0 2px rgba(22,163,74,0.10) !important;
        }

        /* ── Password strength ── */
        #pw-strength-bar {
            height: 4px;
            border-radius: 4px;
            margin-top: 5px;
            background: #e5e7eb;
        }
        #pw-strength-bar .bar-fill {
            height: 4px;
            border-radius: 4px;
            transition: width .3s, background .3s;
        }
        #pw-strength-label {
            font-size: 10px;
            margin-top: 2px;
            font-weight: 600;
        }

        /* ── Step icons (FA) ── */
        .step-icon i {
            font-size: 14px;
            color: var(--accent, #FB9590);
        }
    </style>
</head>
<body>

<div class="reg-wrapper">

    <!-- LEFT PANEL -->
    <div class="reg-left">
        <div class="logo-box">
            <div class="logo-circle">iP</div>
            <div class="logo-text">
                <h2>iPOS</h2>
                <p>I Pay, I Order, I Serve</p>
            </div>
        </div>
        <div class="eyebrow-pill">+ Point of Sale</div>
        <h1>Start Managing<br>Your <span>Store Today</span></h1>
        <p>Join iPOS and take full control of your fast food business —
           from menu management to real-time order tracking, all in one place.</p>
        <div class="reg-steps">
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-utensils"></i></div>
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
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="regForm" novalidate>

                <!-- BUSINESS LOGO -->
                <div class="section-divider">
                    <i class="fa-solid fa-image"></i> Business Logo / Profile Picture
                </div>

                <div id="stepShapeLabel" style="font-size:12px;color:#999;text-align:center;margin-bottom:8px;">Step 1: Choose a shape for your logo</div>
                <div id="shapeChooser" style="display:flex;gap:10px;margin-bottom:12px;justify-content:center;">
                    <label class="shape-btn active" onclick="setShape('circle', this)">
                        <div style="width:40px;height:40px;background:var(--accent-deep,#A33757);border-radius:50%;margin:0 auto 4px;"></div>
                        <span>Circle</span>
                        <input type="radio" name="logo_shape" value="circle" checked style="display:none;">
                    </label>
                    <label class="shape-btn" onclick="setShape('square', this)">
                        <div style="width:40px;height:40px;background:var(--accent-deep,#A33757);border-radius:0;margin:0 auto 4px;"></div>
                        <span>Square</span>
                        <input type="radio" name="logo_shape" value="square" style="display:none;">
                    </label>
                    <label class="shape-btn" onclick="setShape('rounded', this)">
                        <div style="width:40px;height:40px;background:var(--accent-deep,#A33757);border-radius:12px;margin:0 auto 4px;"></div>
                        <span>Rounded</span>
                        <input type="radio" name="logo_shape" value="rounded" style="display:none;">
                    </label>
                </div>

                <div id="stepUploadLabel" style="font-size:12px;color:#999;text-align:center;margin-bottom:8px;">Step 2: Upload your logo</div>
                <div id="logoUploadArea" class="logo-upload-area" onclick="document.getElementById('logoInput').click()">
                    <div id="logoPlaceholder">
                        <div class="logo-upload-icon"><i class="fa-solid fa-camera"></i></div>
                        <p>Click to upload your business logo</p>
                        <small>JPG, PNG, WEBP &middot; Max 2MB &middot; Recommended 500&times;500px</small>
                    </div>
                </div>

                <div id="logoPreviewWrap" style="display:none;text-align:center;margin-bottom:12px;">
                    <img id="logoPreview" style="width:120px;height:120px;object-fit:cover;border:3px solid #A33757;transition:border-radius 0.3s;display:block;margin:0 auto 8px;" alt="Logo Preview">
                    <button type="button" onclick="changeLogo()" style="margin-top:8px;font-size:11px;color:#A33757;background:none;border:none;cursor:pointer;text-decoration:underline;">
                        <i class="fa-solid fa-rotate"></i> Change Logo
                    </button>
                </div>

                <input type="file" id="logoInput" name="logo" accept="image/*" style="display:none;" onchange="previewLogo(this)">
                <canvas id="logoCanvas" style="display:none;"></canvas>

                <!-- OWNER INFORMATION -->
                <div class="section-divider">
                    <i class="fa-solid fa-user"></i> Owner Information
                </div>
                <div class="form-row">
                    <div>
                        <div class="input-group" id="grp-fullname">
                            <span><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="fullname" id="fullname"
                                   placeholder="Full Name"
                                   value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                                   autocomplete="name" required>
                        </div>
                        <p class="field-error" id="err-fullname">Full name is required.</p>
                    </div>
                    <div>
                        <div class="input-group" id="grp-phone">
                            <span><i class="fa-solid fa-phone"></i></span>
                            <input type="text" name="phone_number" id="phone_number"
                                   placeholder="Contact Number"
                                   value="<?= htmlspecialchars($old['phone_number'] ?? '') ?>"
                                   autocomplete="tel" maxlength="11">
                        </div>
                        <p class="field-error" id="err-phone">Enter a valid PH mobile number (e.g. 09XXXXXXXXX).</p>
                    </div>
                </div>
                <div class="input-group" id="grp-email">
                    <span><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" id="email"
                           placeholder="Email Address"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>
                <p class="field-error" id="err-email">Enter a valid email address.</p>

                <!-- BUSINESS INFORMATION -->
                <div class="section-divider">
                    <i class="fa-solid fa-store"></i> Business Information
                </div>
                <div class="form-row">
                    <div>
                        <div class="input-group" id="grp-fastfood_name">
                            <span><i class="fa-solid fa-burger"></i></span>
                            <input type="text" name="fastfood_name" id="fastfood_name"
                                   placeholder="Business Name"
                                   value="<?= htmlspecialchars($old['fastfood_name'] ?? '') ?>"
                                   autocomplete="organization" required>
                        </div>
                        <p class="field-error" id="err-fastfood_name">Business name is required.</p>
                    </div>
                    <div>
                        <div class="input-group" id="grp-business_type">
                            <span><i class="fa-solid fa-shop"></i></span>
                            <select name="business_type" id="business_type">
                                <option value="">Business Type</option>
                                <?php foreach(['Fast Food','Restaurant','Cafe','Food Stall','Catering','Bakery','Other'] as $type): ?>
                                    <option value="<?= $type ?>" <?= ($old['business_type'] ?? '') === $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="select-caret"><i class="fa-solid fa-chevron-down"></i></span>
                        </div>
                        <p class="field-error" id="err-business_type">Please select a business type.</p>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <div class="input-group" id="grp-tin_number">
                            <span><i class="fa-solid fa-id-card"></i></span>
                            <input type="text" name="tin_number" id="tin_number"
                                   placeholder="BIR TIN Number (000-000-000)"
                                   value="<?= htmlspecialchars($old['tin_number'] ?? '') ?>"
                                   maxlength="15">
                        </div>
                        <p class="field-error" id="err-tin_number">Enter a valid TIN (e.g. 000-000-000).</p>
                    </div>
                    <div>
                        <div class="input-group" id="grp-dti_sec_number">
                            <span><i class="fa-solid fa-clipboard"></i></span>
                            <input type="text" name="dti_sec_number" id="dti_sec_number"
                                   placeholder="DTI/SEC Reg. Number"
                                   value="<?= htmlspecialchars($old['dti_sec_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="input-group" id="grp-business_permit">
                    <span><i class="fa-solid fa-file-lines"></i></span>
                    <input type="text" name="business_permit" id="business_permit"
                           placeholder="Business Permit Number"
                           value="<?= htmlspecialchars($old['business_permit'] ?? '') ?>">
                </div>

                <!-- BUSINESS LOCATION -->
                <div class="section-divider">
                    <i class="fa-solid fa-location-dot"></i> Business Location
                </div>
                <div class="input-group" id="grp-address">
                    <span><i class="fa-solid fa-house"></i></span>
                    <input type="text" name="address" id="address"
                           placeholder="Complete Address (Street, Barangay)"
                           value="<?= htmlspecialchars($old['address'] ?? '') ?>" required>
                </div>
                <p class="field-error" id="err-address">Complete address is required.</p>

                <div class="form-row">
                    <div>
                        <div class="input-group" id="grp-province">
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
                            <span class="select-caret"><i class="fa-solid fa-chevron-down"></i></span>
                        </div>
                        <p class="field-error" id="err-province">Please select a province.</p>
                    </div>
                    <div>
                        <div class="input-group" id="grp-city">
                            <span><i class="fa-solid fa-city"></i></span>
                            <select name="city" id="city" required>
                                <option value="">Select City / Municipality</option>
                                <?php
                                $oldProv = $old['province'] ?? '';
                                $oldCity = $old['city'] ?? '';
                                if ($oldProv && isset($ph_data[$oldProv])) {
                                    foreach($ph_data[$oldProv] as $c) {
                                        $sel = ($oldCity === $c) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($c) . "\" $sel>" . htmlspecialchars($c) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <span class="select-caret"><i class="fa-solid fa-chevron-down"></i></span>
                        </div>
                        <p class="field-error" id="err-city">Please select a city / municipality.</p>
                    </div>
                </div>

                <div class="input-group" id="grp-zip_code">
                    <span><i class="fa-solid fa-location-pin"></i></span>
                    <input type="text" name="zip_code" id="zip_code"
                           placeholder="ZIP Code"
                           value="<?= htmlspecialchars($old['zip_code'] ?? '') ?>"
                           maxlength="4" inputmode="numeric" pattern="\d{4}">
                </div>
                <p class="field-error" id="err-zip_code">ZIP code must be exactly 4 digits.</p>

                <!-- ACCOUNT CREDENTIALS -->
                <div class="section-divider">
                    <i class="fa-solid fa-lock"></i> Account Credentials
                </div>
                <div class="input-group" id="grp-username">
                    <span><i class="fa-solid fa-tag"></i></span>
                    <input type="text" name="username" id="username"
                           placeholder="Username"
                           value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>
                <p class="field-error" id="err-username">Username must be 4–20 characters (letters, numbers, underscore).</p>

                <div class="form-row">
                    <div>
                        <div class="input-group" id="grp-password">
                            <span><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" id="password"
                                   placeholder="Password" autocomplete="new-password" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePassword('password')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div id="pw-strength-bar"><div class="bar-fill" style="width:0%;"></div></div>
                        <p id="pw-strength-label" style="color:#aaa;"></p>
                        <p class="field-error" id="err-password">Password must be at least 6 characters.</p>
                    </div>
                    <div>
                        <div class="input-group" id="grp-confirm_password">
                            <span><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   placeholder="Confirm Password" autocomplete="new-password" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePassword('confirm_password')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <p class="field-error" id="err-confirm_password">Passwords do not match.</p>
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

<!-- Philippines City Data -->
<script>
const PH_DATA = <?= json_encode($ph_data, JSON_UNESCAPED_UNICODE) ?>;

/* Province → City dropdown */
document.getElementById('province').addEventListener('change', function () {
    const citySelect = document.getElementById('city');
    const province   = this.value;
    citySelect.innerHTML = '<option value="">Select City / Municipality</option>';
    if (province && PH_DATA[province]) {
        PH_DATA[province].forEach(function (city) {
            const opt = document.createElement('option');
            opt.value       = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
        });
    }
    validateSelect('province');
    citySelect.value = '';
    clearField('city');
});

/* ============================================================
   VALIDATION HELPERS
============================================================ */
function showError(fieldId, msg) {
    const err = document.getElementById('err-' + fieldId);
    const grp = document.getElementById('grp-' + fieldId);
    if (err) { err.textContent = msg; err.style.display = 'block'; }
    if (grp) { grp.classList.add('is-invalid'); grp.classList.remove('is-valid'); }
}
function clearField(fieldId) {
    const err = document.getElementById('err-' + fieldId);
    const grp = document.getElementById('grp-' + fieldId);
    if (err) err.style.display = 'none';
    if (grp) grp.classList.remove('is-invalid', 'is-valid');
}
function markValid(fieldId) {
    const err = document.getElementById('err-' + fieldId);
    const grp = document.getElementById('grp-' + fieldId);
    if (err) err.style.display = 'none';
    if (grp) { grp.classList.remove('is-invalid'); grp.classList.add('is-valid'); }
}

function validateRequired(id, label) {
    const val = document.getElementById(id).value.trim();
    if (!val) { showError(id, label + ' is required.'); return false; }
    markValid(id); return true;
}
function validateFullname() {
    const v = document.getElementById('fullname').value.trim();
    if (!v) { showError('fullname', 'Full name is required.'); return false; }
    if (v.length < 2) { showError('fullname', 'Full name is too short.'); return false; }
    markValid('fullname'); return true;
}
function validateEmail() {
    const v = document.getElementById('email').value.trim();
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!v) { showError('email', 'Email address is required.'); return false; }
    if (!re.test(v)) { showError('email', 'Enter a valid email address.'); return false; }
    markValid('email'); return true;
}
function validatePhone() {
    const v = document.getElementById('phone_number').value.trim();
    if (!v) { clearField('phone_number'); return true; }
    const re = /^(09|\+639)\d{9}$/;
    if (!re.test(v)) { showError('phone_number', 'Enter a valid PH mobile number (e.g. 09XXXXXXXXX).'); return false; }
    markValid('phone_number'); return true;
}
function validateTin() {
    const v = document.getElementById('tin_number').value.trim();
    if (!v) { clearField('tin_number'); return true; }
    const re = /^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/;
    if (!re.test(v)) { showError('tin_number', 'Format: 000-000-000 or 000-000-000-00000'); return false; }
    markValid('tin_number'); return true;
}
function validateSelect(id) {
    const v = document.getElementById(id).value;
    if (!v) {
        const labels = { province: 'Province', city: 'City / Municipality', business_type: 'Business type' };
        showError(id, 'Please select a ' + (labels[id] || id) + '.'); return false;
    }
    markValid(id); return true;
}
function validateZip() {
    const v = document.getElementById('zip_code').value.trim();
    if (!v) { clearField('zip_code'); return true; }
    if (!/^\d{4}$/.test(v)) { showError('zip_code', 'ZIP code must be exactly 4 digits.'); return false; }
    markValid('zip_code'); return true;
}
function validateUsername() {
    const v = document.getElementById('username').value.trim();
    if (!v) { showError('username', 'Username is required.'); return false; }
    if (!/^[a-zA-Z0-9_]{4,20}$/.test(v)) {
        showError('username', 'Username: 4–20 characters, letters/numbers/underscore only.'); return false;
    }
    markValid('username'); return true;
}
function validatePassword() {
    const v = document.getElementById('password').value;
    updateStrength(v);
    if (!v) { showError('password', 'Password is required.'); return false; }
    if (v.length < 6) { showError('password', 'Password must be at least 6 characters.'); return false; }
    markValid('password'); return true;
}
function validateConfirm() {
    const p  = document.getElementById('password').value;
    const cp = document.getElementById('confirm_password').value;
    if (!cp) { showError('confirm_password', 'Please confirm your password.'); return false; }
    if (p !== cp) { showError('confirm_password', 'Passwords do not match.'); return false; }
    markValid('confirm_password'); return true;
}

function updateStrength(pw) {
    let score = 0;
    if (pw.length >= 8)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const bar   = document.querySelector('#pw-strength-bar .bar-fill');
    const label = document.getElementById('pw-strength-label');
    const levels = [
        { pct: '0%',   color: '#e5e7eb', text: '' },
        { pct: '33%',  color: '#ef4444', text: 'Weak' },
        { pct: '55%',  color: '#f97316', text: 'Fair' },
        { pct: '78%',  color: '#eab308', text: 'Good' },
        { pct: '100%', color: '#22c55e', text: 'Strong' },
    ];
    const lvl = levels[Math.min(score, 4)];
    bar.style.width      = lvl.pct;
    bar.style.background = lvl.color;
    label.textContent    = lvl.text;
    label.style.color    = lvl.color;
}

function validateDti() {
    const v = document.getElementById('dti_sec_number').value.trim();
    if (!v) { clearField('dti_sec_number'); return true; }
    const re = /^[A-Z0-9]{2,}[-\s]?[A-Z0-9]{2,}([-\s]?[A-Z0-9]+)*$/i;
    if (v.length < 5 || v.length > 30 || !re.test(v)) {
        showError('dti_sec_number', 'Enter a valid DTI/SEC number (e.g. 12345678-9012 or CS201812345).');
        return false;
    }
    markValid('dti_sec_number'); return true;
}

function validateBusinessPermit() {
    const v = document.getElementById('business_permit').value.trim();
    if (!v) { clearField('business_permit'); return true; }
    const re = /^[A-Z0-9][A-Z0-9\-\/\s]{2,28}[A-Z0-9]$/i;
    if (!re.test(v) || v.length < 4 || v.length > 30) {
        showError('business_permit', 'Enter a valid permit number (e.g. BP-2024-001234).');
        return false;
    }
    markValid('business_permit'); return true;
}

/* Blur listeners */
document.getElementById('fullname').addEventListener('blur',        validateFullname);
document.getElementById('email').addEventListener('blur',           validateEmail);
document.getElementById('phone_number').addEventListener('blur',    validatePhone);
document.getElementById('fastfood_name').addEventListener('blur',   () => validateRequired('fastfood_name', 'Business name'));
document.getElementById('business_type').addEventListener('change', () => validateSelect('business_type'));
document.getElementById('tin_number').addEventListener('blur',      validateTin);
document.getElementById('dti_sec_number').addEventListener('blur',  validateDti);
document.getElementById('business_permit').addEventListener('blur', validateBusinessPermit);
document.getElementById('address').addEventListener('blur',         () => validateRequired('address', 'Complete address'));
document.getElementById('province').addEventListener('blur',        () => validateSelect('province'));
document.getElementById('city').addEventListener('blur',            () => validateSelect('city'));
document.getElementById('zip_code').addEventListener('blur',        validateZip);
document.getElementById('username').addEventListener('blur',        validateUsername);
document.getElementById('password').addEventListener('input',       validatePassword);
document.getElementById('confirm_password').addEventListener('blur',validateConfirm);

/* Auto-format TIN */
document.getElementById('tin_number').addEventListener('input', function () {
    let v = this.value.replace(/[^\d]/g, '');
    if (v.length > 3 && v.length <= 6)  v = v.slice(0,3) + '-' + v.slice(3);
    else if (v.length > 6 && v.length <= 9) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
    else if (v.length > 9) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6,9) + '-' + v.slice(9,14);
    this.value = v;
});

document.getElementById('zip_code').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});
document.getElementById('phone_number').addEventListener('input', function () {
    this.value = this.value.replace(/[^\d+]/g, '').slice(0, 13);
});

/* Form submit */
document.getElementById('regForm').addEventListener('submit', function (e) {
    const checks = [
        validateFullname(),
        validateEmail(),
        validatePhone(),
        validateRequired('fastfood_name', 'Business name'),
        validateSelect('business_type'),
        validateTin(),
        validateDti(),
        validateBusinessPermit(),
        validateRequired('address', 'Complete address'),
        validateSelect('province'),
        validateSelect('city'),
        validateZip(),
        validateUsername(),
        validatePassword(),
        validateConfirm(),
    ];
    if (checks.includes(false)) {
        e.preventDefault();
        const firstErr = document.querySelector('.input-group.is-invalid');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

/* Password toggle */
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const btn   = input.closest('.input-group').querySelector('.toggle-password i');
    if (input.type === 'password') {
        input.type = 'text';
        btn.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        btn.classList.replace('fa-eye-slash', 'fa-eye');
    }
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