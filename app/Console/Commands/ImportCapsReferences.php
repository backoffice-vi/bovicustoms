<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsReferences extends Command
{
    protected $signature = 'caps:import-refs';
    protected $description = 'Import official CAPS reference data (Countries, Carriers, Ports)';

    public function handle()
    {
        $this->info('Importing CAPS reference data...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $page = WebFormPage::where('web_form_target_id', $target->id)
            ->where('name', 'TD Data Entry')
            ->first();

        if (!$page) {
            $this->error('TD Data Entry page not found.');
            return 1;
        }

        // 1. Import Ports
        $this->importPorts($page);

        // 2. Import Countries
        $this->importCountries($page);

        // 3. Import Carriers
        $this->importCarriers($page);

        $this->info('Reference data import complete!');
        return 0;
    }

    protected function importPorts($page)
    {
        $this->info('Importing Ports...');
        
        $portsRaw = <<<EOT
AM ANEGADA MARINA
AP ANEGADA AIRPORT
BI BEEF ISLAND AIRPORT
FB FISH BAY
GC VIRGIN GORDA GUN CREEK
GHM GREAT HABOUR
IAT FIRST TIME HOME BUILDERS
JV JOST VAN DYKE
ON O NEAL PIPELINE
PA POST OFFICE - AIRPORT ARRIVAL
PC PETROLEUM AND CONCESSIONS
PCA PETROLEUM AND CONCESSIONS AIR
PO POCKWOOD POND
PP PORT PURCELL
PS POST OFFICE - SEAPORT ARRIVAL
RT ROAD TOWN WHARF
ST ST. THOMAS BAY
VA VIRGIN GORDA AIRPORT
VM VIRGIN GORDA MARINA
VP VIRGIN GORDA CARGO PORT
WE WEST END
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Port of Arrival')
            ->first();

        if ($mapping) {
            // Clear existing non-default values to avoid duplicates
            WebFormDropdownValue::where('web_form_field_mapping_id', $mapping->id)
                ->where('is_default', false)
                ->delete();

            $lines = explode("\n", $portsRaw);
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Split by first space: Code is usually 2-3 chars
                $parts = explode(' ', $line, 2);
                $code = $parts[0];
                $name = $parts[1] ?? '';

                // Generate local matches
                $localMatches = [$name, $code];
                
                // Specific common variations
                if ($code === 'PP') $localMatches = array_merge($localMatches, ['Road Town', 'Tortola']);
                if ($code === 'BI') $localMatches[] = 'Beef Island';
                if ($code === 'WE') $localMatches[] = 'West End';
                if ($code === 'JV') $localMatches[] = 'JVD';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                    'is_default' => ($code === 'PP'), // Default to Port Purcell
                ]);
            }
            $this->info("Imported " . count($lines) . " ports.");
        }
    }

    protected function importCountries($page)
    {
        $this->info('Importing Countries...');

        // Raw data (abbreviated for the command, but typically would read from file)
        // I'll include the full list provided in previous context
        $countriesRaw = <<<EOT
AF AFGHANISTAN
AL ALBANIA
DZ ALGERIA
AS AMERICAN SAMOA
AD ANDORRA
AO ANGOLA
AI ANGUILLA
AQ ANTARCTICA
AG ANTIGUA AND BARBUDA
AR ARGENTINA
AM ARMENIA
AW ARUBA
AU AUSTRALIA
AT AUSTRIA
AZ AZERBAIJAN
BS BAHAMAS
BH BAHRAIN
BD BANGLADESH
BB BARBADOS
BY BELARUS
BE BELGIUM
BZ BELIZE
BJ BENIN
BM BERMUDA
BT BHUTAN
BO BOLIVIA
BA BOSNIA AND HERZEGOVINA
BW BOTSWANA
BV BOUVET ISLAND
BR BRAZIL
IO BRITISH INDIAN OCEAN TERRITORY
BN BRUNEI DARUSSALAM
BG BULGARIA
BF BURKINA FASO
BI BURUNDI
KH CAMBODIA
CM CAMEROON
CA CANADA
CV CAPE VERDE
KY CAYMAN ISLANDS
CF CENTRAL AFRICAN REPUBLIC
TD CHAD
CL CHILE
CN CHINA
CX CHRISTMAS ISLAND
CC COCOS (KEELING) ISLANDS
CO COLOMBIA
KM COMOROS
CG CONGO
CD CONGO, THE DEMOCRATIC REPUBLIC OF THE
CK COOK ISLANDS
CR COSTA RICA
CI COTE D'IVOIRE
HR CROATIA
CU CUBA
CY CYPRUS
CZ CZECH REPUBLIC
DK DENMARK
DJ DJIBOUTI
DM DOMINICA
DO DOMINICAN REPUBLIC
EC ECUADOR
EG EGYPT
SV EL SALVADOR
GQ EQUATORIAL GUINEA
ER ERITREA
EE ESTONIA
ET ETHIOPIA
FK FALKLAND ISLANDS (MALVINAS)
FO FAROE ISLANDS
FJ FIJI
FI FINLAND
FR FRANCE
GF FRENCH GUIANA
PF FRENCH POLYNESIA
TF FRENCH SOUTHERN TERRITORIES
GA GABON
GM GAMBIA
GE GEORGIA
DE GERMANY
GH GHANA
GI GIBRALTAR
GR GREECE
GL GREENLAND
GD GRENADA
GP GUADELOUPE
GU GUAM
GT GUATEMALA
GG GUERNSEY
GN GUINEA
GW GUINEA-BISSAU
GY GUYANA
HT HAITI
HM HEARD ISLAND AND MCDONALD ISLANDS
VA HOLY SEE (VATICAN CITY STATE)
HN HONDURAS
HK HONG KONG
HU HUNGARY
IS ICELAND
IN INDIA
ID INDONESIA
IR IRAN, ISLAMIC REPUBLIC OF
IQ IRAQ
IE IRELAND
IM ISLE OF MAN
IL ISRAEL
IT ITALY
JM JAMAICA
JP JAPAN
JE JERSEY
JO JORDAN
KZ KAZAKHSTAN
KE KENYA
KI KIRIBATI
KR KOREA, REPUBLIC OF
KP KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF
KW KUWAIT
KG KYRGYZSTAN
LA LAO PEOPLE'S DEMOCRATIC REPUBLIC
LV LATVIA
LB LEBANON
LS LESOTHO
LR LIBERIA
LY LIBYAN ARAB JAMAHIRIYA
LI LIECHTENSTEIN
LT LITHUANIA
LU LUXEMBOURG
MO MACAO
MK MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF
MG MADAGASCAR
MW MALAWI
MY MALAYSIA
MV MALDIVES
ML MALI
MT MALTA
MH MARSHALL ISLANDS
MQ MARTINIQUE
MR MAURITANIA
MU MAURITIUS
YT MAYOTTE
MX MEXICO
FM MICRONESIA, FEDERATED STATES OF
MD MOLDOVA, REPUBLIC OF
MC MONACO
MN MONGOLIA
ME MONTENEGRO
MS MONTSERRAT
MA MOROCCO
MZ MOZAMBIQUE
MM MYANMAR
NA NAMIBIA
NR NAURU
NP NEPAL
NL NETHERLANDS
AN NETHERLANDS ANTILLES
NC NEW CALEDONIA
NZ NEW ZEALAND
NI NICARAGUA
NE NIGER
NG NIGERIA
NU NIUE
NF NORFOLK ISLAND
MP NORTHERN MARIANA ISLANDS
NO NORWAY
OM OMAN
PK PAKISTAN
PW PALAU
PS PALESTINIAN TERRITORY, OCCUPIED
PA PANAMA
PG PAPUA NEW GUINEA
PY PARAGUAY
PE PERU
PH PHILIPPINES
PN PITCAIRN
PL POLAND
PT PORTUGAL
PR PUERTO RICO
QA QATAR
RE REUNION
RO ROMANIA
RU RUSSIAN FEDERATION
RW RWANDA
SH SAINT HELENA
KN SAINT KITTS AND NEVIS
LC SAINT LUCIA
PM SAINT PIERRE AND MIQUELON
VC SAINT VINCENT AND THE GRENADINES
WS SAMOA
SM SAN MARINO
ST SAO TOME AND PRINCIPE
SA SAUDI ARABIA
SN SENEGAL
RS SERBIA
SC SEYCHELLES
SL SIERRA LEONE
SG SINGAPORE
SK SLOVAKIA
SI SLOVENIA
SB SOLOMON ISLANDS
SO SOMALIA
ZA SOUTH AFRICA
GS SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS
ES SPAIN
LK SRI LANKA
QN ST MAARTEN (DUTCH)
QO ST MARTIN (FRENCH)
SD SUDAN
SR SURINAME
SJ SVALBARD AND JAN MAYEN
SZ SWAZILAND
SE SWEDEN
CH SWITZERLAND
SY SYRIAN ARAB REPUBLIC
TW TAIWAN, PROVINCE OF CHINA
TJ TAJIKISTAN
TZ TANZANIA, UNITED REPUBLIC OF
TH THAILAND
TL TIMOR-LESTE
TG TOGO
TK TOKELAU
TO TONGA
TT TRINIDAD AND TOBAGO
TN TUNISIA
TR TURKEY
TM TURKMENISTAN
TC TURKS AND CAICOS ISLANDS
TV TUVALU
UG UGANDA
UA UKRAINE
AE UNITED ARAB EMIRATES
GB UNITED KINGDOM
US UNITED STATES
UM UNITED STATES MINOR OUTLYING ISLANDS
UY URUGUAY
UZ UZBEKISTAN
VU VANUATU
VE VENEZUELA
VN VIET NAM
VG VIRGIN ISLANDS, BRITISH
VI VIRGIN ISLANDS, U.S
WF WALLIS AND FUTUNA
EH WESTERN SAHARA
YE YEMEN
ZM ZAMBIA
ZW ZIMBABWE
AX ALAND ISLANDS
EOT;

        // Apply to all country fields
        $countryFields = [
            'Supplier Country',
            'Country of Direct Shipment',
            'Country of Original Shipment',
            'Country of Origin (Item)'
        ];

        foreach ($countryFields as $fieldLabel) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $fieldLabel)
                ->first();

            if ($mapping) {
                WebFormDropdownValue::where('web_form_field_mapping_id', $mapping->id)
                    ->where('is_default', false)
                    ->delete();

                $lines = explode("\n", $countriesRaw);
                foreach ($lines as $index => $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $parts = explode(' ', $line, 2);
                    $code = $parts[0];
                    $name = $parts[1] ?? '';

                    $localMatches = [$name, $code];
                    
                    // Common variations
                    if ($code === 'US') $localMatches = array_merge($localMatches, ['USA', 'United States of America']);
                    if ($code === 'GB') $localMatches = array_merge($localMatches, ['UK', 'Great Britain', 'England']);
                    if ($code === 'VI') $localMatches[] = 'USVI';

                    WebFormDropdownValue::create([
                        'web_form_field_mapping_id' => $mapping->id,
                        'option_value' => $code,
                        'option_label' => "$code - $name",
                        'local_matches' => $localMatches,
                        'sort_order' => $index,
                        'is_default' => ($code === 'US'),
                    ]);
                }
                $this->info("Imported countries for $fieldLabel");
            }
        }
    }

    protected function importCarriers($page)
    {
        $this->info('Importing Carriers...');

        $carriersRaw = <<<EOT
AA AMERICAN AIRLINES
AAM AIR AMERICA
AAN AAL NEWCASTLE
ACN AIR CENTER
ADA ADELINA
ADD ADDIE
ADP ADMIRAL PRIDE
AIF AIR FLAMENCO
AJI AMERIJET INTERNATIONAL
ALO AMBER LAGOON
AMA AMAICA II
AMF AMERIFLIGHT
AMG AMIGO
AMI MV ARAMIS
ANA ANASURYA
ANJ AN JI JIANG
ANT ANTISANA
ARR ARMED RESPONDER XV
ARS ARESSA 001
AS AIR SUNSHINE
ATL ATLANTIC
AUT AUTSHUMATO
BA BVI AIRWAYS
BAE BAHAMAS EXPRESS
BAL BARGE LM2411
BBR BBC RHINE
BDC BEDFORD CASTLE
BFT BF TIMARU
BIF BAD FISH
BKA BARGE KMILA
BKG BULK KING
BL2 BELLE LASHUN II
BLH BLACK HAWK
BLM BLUE MOON
BLS BELLE LASHUN
BMH BARGE MARILIN H
BNG BONNIE G
BOM BOMBER CHARGER
BRD BBC RHEIDERLAND
BTM BTMAX1
BVP BVI PATRIOT
CAB CANEEL BAY III
CAE CAERUS
CAL CARIBBEAN AIRLINES LIMITED
CAP CARIBBEAN PRIDE
CAW CARIBBEAN WINGS
CBF CARIBBEAN FORCE
CEB C ELIZABETH II
CIC CIC III
CKP C KEMPTON
CMT CARIBBEAN MARITIME
COD COMBIDOCK
CON CONQUEROR
COS COSIMA PG
CPA CAPE AIR
CPC CAPT. CHRISSY
CPV CAPTAIN VIC
CQA CONQUEST AIR
CRE CARIBE 400
CRP CARIBBEAN PERFORMANCE
CTC CTC02404
CYN CYGNUS
DAB DANIEL B
DAD DADCHANDRA
DAL DALMAZIA
DBD DEVBULK DENIZ
DER DERICE W
DHL DHL
DIY DIYA
DLA DONA LILA
DLH DILAILAH
DME DREAM 4 EVER
DML DREAM LOVER
DNL DONA LIPA
DNM DON MENCHO
DPI DONA PROVI
DOA DON ANDRES
DOL DONA LUISA
DRA DREAM ALFORD
DRS DREAM SPEED
DRW DESAL 4
DTW DRIFT WOOD
DYB DYNA BULK
EEX EG EXPRESS
EFF MV JEFFERSON-IMO
EGP E G POWER
EII EAST PACK II
EL1 EL SUENO 1
EL2 EL SUENO II
ELC ELIZABETH C
ELS EL SUENO
ELT ELITTA II
EMO EL MORRO
EMR EMPRENDEDOR
EMY EMILY PG
ENE ENTERPRISE
EPC EPIC CALEDONIA
EPI EAST PACK I
EVE EVIE PG
EZO EZONE
FED FEDERAL EXPRESS
FEF FALK EX FLAKNESS
FER FERREL
FF ROAD TOWN FAST FERRY
FIT FIORA TOPIC
FLY FLY BVI
FRC FAIR CHANCE
FST FOUR STAR CARGO
FTW FAST WIL
GAF GAS FLAWLESS
GAR GARDENIA K
GBY GIS BLAKELY
GII GENERAL II
GNC GREEN CHIEF
GNL GENERAL LEE
GRS GLORIOUS
GZY GIS-ZACHARY
HAM AHS HAMBURG
HCO HC OPAL
HEN HEIN
HOC HOEGH CARIBIA
HZL HAZEL
IAM INDUSTRIAL AIM
IB ISLAND BIRDS
ICA INTERCARIBBEAN AIRLINE
IDS ISLAND SCOUT
IEW IDLE WILD
IIT INTER-ISLAND TRANSPORT
INS INAGUA SEA
INT INT L GENERAL
ISB ISABELLA B
ISE ISLAND EXPRESS
ISS ISLAND SEAL
ISV ISLAND VIC
IVR IVY ROSE
JAC JACQUELINE C
JAH JACONINA H
JER JERIMY
JM1 JMC-164
JMC JMC-3080
JTC JANET C
JUC JULIE C
JUW JUS WRONG
KAK KAS KIARA
KAR KARMA
KOO MV KOOLE K
KTD KRAUSAND
KTF KESTREL FISHER
KUI KUKUI
KUN MV YI KUN DAO YUAN
LAD LADY DIANN
LAK LADY KISHMA
LAS LADY SUSAN
LAU L AUDACE
LC LADY CANEEL 2
LDC LADY CAROLINE
LDK LADY KEISH
LDV LADY VICTORIA
LEG LEGACY
LIT LITA
LLX LADY LINX
LM2 LM2702
LND LINDA D
LPG LESLEY PG
LRY LADY ROMNEY
LT LIAT AIRLINES
LTB LIMETREE BAY
LYS MV LYKTOS
MAA MAMMA AFRIQUE
MAB MAX BARGE 3000
MAG MARY G
MAK MAKANA
MAR MARIE ELISE
MAT MV ATLANTIC
MCH MIDNIGHT CHIEF
MDH MIDGARD HUGO
MEH MEGOLLY HAWK
MEL M/V MELINA
MGA MISS GLANVILLIA
MGJ MIDGARD JERRY
MHK MIDGARD HUGO/KUKUI
MIC MIDNIGHT COAST
MID MIDAS
MIM MIMER
MIS MIDNIGHT STONE
MIT MIDNIGHT TIDE
MLA MICHEAL A
MNC MIDNIGHT CZAR
MND MIDNIGHT DREAM
MNH MARILIN H
MNP MIDNIGHT PEARL
MNR MIDNIGHT RIVER
MNW MIDNIGHT WOLF
MRE MIDNIGHT REIGN
MRI MAORI
MRN MR NITROX
MTA MS TACOMA
MUP MUTTY S PRIDE
MVI MARCO VI
MVL MV LUCAS JOHN
MVM MV MISTRAL
MVY MV YI KUN DE YUAN
NAN NAN
NOH NORMA H II
NOR NORABEL
NR NOT RELATED VESSEL ID FOUND
NS ADVENTURE
NSE NATIVE SON EXPRESS
NSI NSI
NTI N TIGA
NUJ NURSE JEAN
NVS NAVI STAR
OB5 OSLO BULK 5
OB8 OSLO BULK 8
OB9 OSLO BULK 9
OCE OCEAN 17
OCS OCEAN SPIRIT
OPR OCEAN PRINCESS
ORI ORIOLE
ORN ORION
OS2 OSLO CARRIER 2
OTH OTHER (FOR ONE-OFF VESSELS)
OZA OZAMA
PAN PANDA PG
PEN PENGUIN
PII PTI BLEU II
PKT PROMISE KEPT
PLI PELAGIANI
PLP PROPEL PROSPERITY
POS POSEN
PRD PRIDE LA DOMINIQUE
PRO PROVINCETOWN
PSM PRINCESS SAMAFIA
RCO M/V MARCO VI
RDS REED DANOS
REB REBECCA
REF REANE F
RII RIO CARIB II
ROC ROCKET
RPG ROSE PG
SAF SEA FALCON
SBS SABRE SPIRIT
SCS SCOTTY SKY
SEB SEA BOURNE
SEH SEA HUSTLER
SEM SEVEN MAKO
SEN SENTRY
SER SERVER
SES SEA SEARCH
SFJ SILVERFJORD
SHB SHOUTER B
SK2 SK2
SKS SKY SEAL
SLA SOL AZUL
SLF SILVER FOX
SML STINNES MISTRAL
SMX SIDER MOMPOX
SOP SOPHIA
SP2 SEA SPIRIT II
SPE SPEEDY S FERRY
SPI SPI
SPS STINNES PASSAT
SUQ SUZIE Q
SSA SEVEN STARS AIR CARGO
STE STERLING
STR STRADIONGRACHT
SU1 SUENO-1
SUE SUN EXPRESS
SUN SUNERGON
SVA LPG/C SIGAS SILVIA
SVM SEVEN MARLIN
SWT SHOW TIME
SYM SYDNEY MARIE
SYS SAY SO
SYW SYROS WIND
SZL STINNES ZEPHIR
T55 T55
T56 T-56
TAM BARGE AMELIE
TAN TUG ANUZ
TAU TUG AURORA
TBH TB HAZEL
TDG TODD G
TEE THEREASE
TEN TENACITY I
TES TESSA PG
TEX TROPIC EXPRESS
TFF TORTOLA FAST FERRY
TFL FIRST LIGHT
TGN GREEN NICOYA
TII TRINITY TRANSPORTER
TMA TACOMA
TNT TANZANITE
TOE TORTOLA EXPRESS
TOR TORTOLA PRIDE
TPF TROPIC FREEDOM
TPJ TROPIC JADE
TPL TROPIC LURE
TPM TROPICAL MIST
TPN TROPIC NIGHT
TPO TROPIC OPAL
TPP TROPIC PALM
TPS TROPIC SUN
TPT TROPIC TIDE
TPU TROPIC UNITY
TRO TROJAN
TRT TRINITY TRADEWINDS
TTR TRINITY TRANSPORTER II
TUA TUG EDINA
TUJ TUG JANIKA
TUM TUG MARIMBA
TUW TUG WOOREE
UBO UAL BODEWES
UNS UNISTAR
URA URANUS
VCZ VIKING CONSTANZA
VIA VI AIR LINK
VIP VIRGINIA S PRIDE
VIQ VI QUEST
WAP WARREN PRIDE
WAS WATER SPIRIT
WBD WIND BILD 1810
WBT WINBUILT 303
WHN WEISSHORN
WI WINAIR
WID WINDBUILD 1811
WND WINBUILD 1810
WS2 WATER SPIRIT 2
YAG MV VOYAGER
ZDS ZELADA DESGAGNES
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Carrier ID')
            ->first();

        if ($mapping) {
            WebFormDropdownValue::where('web_form_field_mapping_id', $mapping->id)
                ->where('is_default', false)
                ->delete();

            $lines = explode("\n", $carriersRaw);
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $parts = explode(' ', $line, 2);
                $code = $parts[0];
                $name = $parts[1] ?? '';

                $localMatches = [$name, $code];
                
                // Variations
                if (str_contains($name, 'TROPIC')) $localMatches[] = 'Tropical';
                if ($code === 'FED') $localMatches[] = 'FedEx';
                if ($code === 'DHL') $localMatches[] = 'DHL';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                ]);
            }
            $this->info("Imported carriers");
        }
    }
}
