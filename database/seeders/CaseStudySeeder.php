<?php

namespace Database\Seeders;

use App\Models\PortfolioProject;
use Illuminate\Database\Seeder;

/**
 * The long-form half of each case study.
 *
 * OWNER: read these. They are written from the facts already on record — the
 * CV, the project descriptions and the highlights — expanded into the four
 * things somebody deciding whether to hire actually reads for: what was
 * broken, what was built, how it works, and what it had to survive.
 *
 * Deliberately absent: outcome metrics. "Cut fraud by 40%" is the easiest
 * sentence in the world to write and the hardest to stand behind, and a
 * ministry can check. Everything here is either a design decision or a
 * constraint, both of which are true regardless of how the numbers landed.
 *
 * Kept apart from PortfolioContentSeeder because prose is edited far more
 * often than a slug, a tag or a sort order.
 */
class CaseStudySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->studies() as $slug => $study) {
            PortfolioProject::where('slug', $slug)->update($study);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function studies(): array
    {
        return [

            'ulits' => [
                'role' => 'Lead developer and system architect',
                'period' => 'Eight Tech Consults, for MAAIF',
                'problem' => 'Uganda has millions of cattle and no single record of where any of them are. '
                    .'A district office knew its own herds; the district next door did not. When disease broke '
                    .'out, tracing which animals had moved through an infected area meant phone calls and paper '
                    .'ledgers, and by the time the picture was assembled the animals had moved again. Livestock '
                    .'is also where a great deal of rural wealth sits, so a stolen herd was very hard to prove '
                    .'stolen.',
                'approach' => 'One national register, and a movement permit that cannot be issued without it. '
                    .'Every animal is ear-tagged and registered once, against an owner and a holding. Moving '
                    .'animals between districts requires a permit that names the exact tags travelling, and '
                    .'those tags are re-scanned at each district boundary. If the scan does not match the '
                    .'permit, the consignment stops there.',
                'mechanics' => [
                    'An animal is registered once, on a phone, against its owner, its holding and its GPS position. The tag number becomes its identity for life.',
                    'A movement permit lists the specific tags travelling, the route and the window it is valid for. It is issued by a district officer, not requested by post.',
                    'Checkpoints re-scan the tags. A tag that is not on the permit, or a permit outside its window, fails at the roadside rather than in an office weeks later.',
                    'Vaccination and treatment are recorded against the tag, so an animal carries its own medical history wherever it goes.',
                    'A disease alert works backwards through movement records: every holding an infected animal passed through is listed in seconds, not weeks.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Android', 'iOS', 'REST API', 'GPS/GIS', 'SMS/USSD gateway'],
                'constraints' => [
                    'No network. Field officers work in districts with no reliable signal, so the app captures everything on the device and uploads when a connection returns. Nothing is lost and nothing waits for coverage.',
                    'No smartphone, sometimes. Farmers get status and alerts over SMS and USSD, because a system only half the country can reach is not a national system.',
                    'Audit trail. It is a government register: every record is attributable to a named officer, and roles decide who can see and change what, across web, Android and iOS alike.',
                    'Staff turnover. District officers rotate. The interface has to be learnable in a morning, and it was. Training ran across multiple districts.',
                ],
            ],

            'school-dynamics' => [
                'role' => 'Lead developer',
                'period' => 'SaaS product, 2,000+ active users',
                'problem' => 'A secondary school runs on half a dozen separate books: a register, a fees ledger, '
                    .'a mark sheet, a timetable pinned to a wall, a library card index. Nothing reconciles. '
                    .'A bursar cannot tell a parent what is owed without finding the right page, a head teacher '
                    .'cannot see attendance until the term ends, and a parent finds out their child has been '
                    .'absent for a week when the report card arrives.',
                'approach' => 'One school record with three doors into it. Parents, teachers and administrators '
                    .'each get a portal that shows only their part, all reading from the same underlying data. '
                    .'The attendance a teacher takes at 08:00 is the attendance a parent sees at 08:05, and the fee '
                    .'a parent pays by Mobile Money clears against the bursar\'s ledger without anybody keying '
                    .'it in twice.',
                'mechanics' => [
                    'Student records, staff, streams and subjects sit in one place. Everything else in the system reads from them rather than keeping its own copy.',
                    'The timetable is generated rather than drawn: it solves for teacher, room and stream at once, so a clash is impossible rather than merely unlikely.',
                    'Attendance is taken on the register screen and is immediately visible to parents. An absence generates an SMS the same morning.',
                    'Fees are collected through MTN and Airtel Money, Visa and bank transfer. A payment reconciles itself against the student\'s account and issues a receipt.',
                    'Examinations, library, transport and hostel each add their own module, and each one reports into the same administrator dashboard.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Mobile Money APIs', 'Visa', 'SMS gateway', 'Email'],
                'constraints' => [
                    'Multi-tenant. It is one platform serving many schools, so a school\'s data has to be invisible to every other school on it, by construction rather than by care.',
                    'Term-time load. Everybody logs in on results day. Reporting is built to answer a large number of parents at once without the bursar\'s screen slowing down.',
                    'Money. Fee collection is real money moving through Mobile Money and card rails, so every transaction is reconciled and receipted, and a failed payment can never leave a student marked as paid.',
                    'Non-technical staff. Bursars and teachers are not IT people. If a screen needs training beyond one session, it is the wrong screen.',
                ],
            ],

            'hospital-management' => [
                'role' => 'Lead developer',
                'period' => 'Global Health Rescue',
                'problem' => 'A patient file lives in a paper folder that is only ever in one place. It is with '
                    .'the doctor, or the lab, or in the records room, or lost. Test results reach the ward a day '
                    .'after the decision that needed them. Pharmacy dispenses against a handwritten note. And an '
                    .'insurance claim assembled from three disconnected records is a claim that gets rejected.',
                'approach' => 'The record moves, not the folder. One electronic health record per patient, which '
                    .'every department writes into and reads from. A doctor ordering a test, the lab running '
                    .'it, the pharmacy dispensing against it and the billing office claiming for it are all '
                    .'looking at the same thing at the same moment.',
                'mechanics' => [
                    'One patient identifier, issued once, carried through admission, ward, clinic, laboratory, pharmacy and billing.',
                    'A clinician orders from the patient record. The order appears in the laboratory or pharmacy queue immediately, and its result comes back onto the same record.',
                    'Appointments and ward beds are scheduled against real availability, so a clinic list cannot be double-booked and a bed cannot be given to two patients.',
                    'Every dispensed drug decrements pharmacy stock, so what the shelf says and what the system says do not drift apart.',
                    'Billing and insurance claims are assembled from what actually happened to the patient, which is why they reconcile.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Role-based access control', 'PDF reporting', 'Encrypted storage'],
                'constraints' => [
                    'Confidentiality. Health records are among the most sensitive data a system can hold. Access is by role and by need, and who opened what is recorded.',
                    'It cannot be down. A ward does not stop at night, so the system has to survive the hours when nobody is watching it.',
                    'Continuity. Clinical staff change shift mid-treatment. The record has to make sense to a nurse or doctor who was not there when it was written.',
                    'Claims discipline. Insurers reject anything inconsistent, so the billing module has to be right the first time rather than corrected later.',
                ],
            ],

            'wildlife-offenders' => [
                'role' => 'Lead developer',
                'period' => 'For the Uganda Wildlife Authority',
                'problem' => 'Wildlife crime is repeat business, and repeat offenders were being treated as first '
                    .'offenders. Each park kept its own records, so a person caught poaching in Murchison Falls '
                    .'and again in Queen Elizabeth was two unrelated names in two unrelated books. Cases were '
                    .'lost between arrest and court because nobody could produce a coherent file.',
                'approach' => 'One national offender register with biometrics attached, and a case that carries '
                    .'its own evidence from the ranger post to the courtroom. If an officer has been caught before, '
                    .'anywhere in the country, the officer at the post knows within seconds.',
                'mechanics' => [
                    'A ranger captures the incident where it happened (offender, offence, location, seizure, photographs) on a mobile device rather than in a notebook.',
                    'Fingerprints and photographs are matched against the national register. A previous record surfaces at the post, not months later in court.',
                    'The case moves through a defined workflow: opened, charged, escalated, in court, closed. Every state change is attributable and timed.',
                    'Seizures are logged as evidence against the case, so what was taken and where it is now is answerable at any point.',
                    'Enforcement analytics aggregate incidents by park, season and offence type, so patrols can be sent where the offences actually are.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Mobile app', 'Biometric matching', 'Analytics dashboards'],
                'constraints' => [
                    'The field is not the office. Incidents happen in parks with no coverage, so capture works offline and syncs when the ranger reaches signal.',
                    'Evidence standards. The file has to survive court, which means an unbroken, timestamped, attributable chain from capture to filing.',
                    'Personal data. This is a register of people accused of crimes. Access is restricted, logged, and narrower than it is comfortable to make it.',
                    'Speed at the point of arrest. A match that takes an hour is a match that arrives after the decision it was meant to inform.',
                ],
            ],

            'seed-tracking' => [
                'role' => 'Developer',
                'period' => 'For MAAIF crop inspection',
                'problem' => 'A farmer buys a bag of certified maize seed, plants it, and half of it does not come '
                    .'up. The bag looked genuine, because counterfeit seed is packaged to look genuine. By the time the '
                    .'failure is obvious the season is gone, and there is no way to trace the bag back to whoever '
                    .'sold it. Subsidy vouchers had the same hole: no way to tell a redeemed voucher from a '
                    .'photocopied one.',
                'approach' => 'Give every certified batch an identity that can be checked in a shop, in thirty '
                    .'seconds, by anyone with a phone. Then record every hand-off along the way, so a bag that '
                    .'appears from nowhere has no history to show and fails the check.',
                'mechanics' => [
                    'Each certified batch is issued a barcode or QR code carrying its identity, not just a number printed on a label.',
                    'The code is scanned at each hand-off (breeder, multiplier, processor, agro-dealer), building a chain of custody that is signed and timestamped at every step.',
                    'A farmer or an inspector scans the bag in the shop. The system answers with the batch, its germination and moisture test results, and where it was multiplied.',
                    'A bag that skips a stage, is scanned twice in two places, or comes from an unregistered dealer is flagged, and the dealer goes onto an inspection list.',
                    'Subsidy vouchers are redeemed against the same scan, so a voucher can be spent once and the redemption is tied to a real batch.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Barcode/QR', 'Mobile scanning', 'Government data exchange'],
                'constraints' => [
                    'It has to work in a shop. The check is done standing at a counter, on whatever handset the person has, in the time before the customer walks away.',
                    'Interoperability. Government systems have to exchange this data, so the formats were agreed rather than invented.',
                    'Volume. Seed moves in large volumes each season. The scan path is the part that has to stay fast when everything else is busy.',
                    'Counterfeiters adapt. Anything printed can be copied, so the answer comes from the chain of custody behind the code, not from the code alone.',
                ],
            ],

            'pwd-observatory' => [
                'role' => 'Developer',
                'period' => 'With the Uganda Communications Commission and NUDIPU',
                'problem' => 'Policy about persons with disabilities was being made without data about persons '
                    .'with disabilities. Nobody could say how many people in a given district had a hearing '
                    .'impairment, how many owned a phone they could actually use, or whether last year\'s '
                    .'programme changed anything. Arguments were won by whoever spoke most confidently.',
                'approach' => 'Build the evidence base, and publish it. A nationwide database collected with the '
                    .'disability associations themselves, with dashboards designed so that a policy officer who '
                    .'is not a statistician can find the number they need and cite it.',
                'mechanics' => [
                    'Data is collected door to door with NUDIPU district associations, by people the communities already know rather than strangers with clipboards.',
                    'Nobody is recorded without consenting to be, and what each record is for is explained at the point it is taken.',
                    'Responses are weighted to the national population, so a sub-region with fewer respondents does not vanish from the picture.',
                    'Dashboards break access to ICT down by sub-region, by type of disability and by round, so change over time is visible rather than asserted.',
                    'The dataset is downloadable with its methodology attached, because evidence nobody can check is not evidence.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Analytics dashboards', 'Data export', 'Accessible front end'],
                'constraints' => [
                    'The platform itself has to be accessible. A disability observatory that a screen reader cannot use would undermine the whole point, so contrast, keyboard use and semantics were requirements, not polish.',
                    'Consent and dignity. These are records about people, held with their agreement, and the system is built to make that agreement real rather than assumed.',
                    'National coverage. All 146 districts, including the ones that are hardest to reach. The ones usually left out of surveys are the point of this one.',
                    'Comparability. Rounds have to line up with each other, so question wording and weighting are fixed rather than improved between rounds.',
                ],
            ],

            'human-rights-reporting' => [
                'role' => 'Developer',
                'period' => 'For CEHURD',
                'problem' => 'Human rights documentation is dangerous to hold and useless if it cannot be found. '
                    .'Cases were being recorded in documents and spreadsheets on individual laptops: a laptop '
                    .'that is lost or seized takes the evidence with it, and a case referred to a lawyer arrives '
                    .'as an email thread nobody can reconstruct. Meanwhile nobody could see patterns across '
                    .'cases, which is where the advocacy value actually is.',
                'approach' => 'One case register with a sealed evidence vault behind it. Everything about a case '
                    .'lives in one place, encrypted, with access held by named people, and every access written to '
                    .'an audit log. Then, on top of that, the trend analysis that turns individual cases into an '
                    .'argument.',
                'mechanics' => [
                    'Intake records the case once, with consent, and issues an identifier that follows it to court.',
                    'Evidence (photographs, medical reports, audio statements, letters) is attached to the case and encrypted at rest.',
                    'Access is held by named people. Opening a sealed item is written to the audit log against that person and a stated reason.',
                    'The case moves through a tracked workflow: intake, sealed, referred to counsel, filed, heard. Nothing advances without leaving a record.',
                    'Trend dashboards aggregate across cases by type, district and time, so documentation becomes evidence for advocacy rather than a filing cabinet.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Encryption at rest', 'Role-based access control', 'Audit logging'],
                'constraints' => [
                    'The threat model is real. This data can put people at risk, so encryption, key custody and access logging were the first decisions rather than a later hardening pass.',
                    'Keys are not held on the platform. Anyone who takes the server does not thereby have the evidence.',
                    'Field intake. Documentation happens away from the office, so capture works there and syncs afterwards.',
                    'Court usefulness. Documentation that cannot be produced coherently to a lawyer has failed, so export was designed alongside capture.',
                ],
            ],

            'ecommerce-realestate' => [
                'role' => 'Full-stack developer',
                'period' => 'AfriInventions & Eight Tech Consults',
                'problem' => 'Two businesses, two entirely different things to sell: physical goods that ship '
                    .'and property that does not. Both were being run out of spreadsheets and WhatsApp. '
                    .'Stock counts were wrong by the time anyone read them, enquiries were lost in a phone, and '
                    .'nobody could answer "what did we actually make this month" without an afternoon of adding '
                    .'up.',
                'approach' => 'One back office, two storefronts. Goods and property are different enough to need '
                    .'different listing types and different journeys, and similar enough that inventory, '
                    .'payments, customers and reporting should not be built twice.',
                'mechanics' => [
                    'A listing is either a product with stock and shipping, or a property with an agent and a viewing. The same catalogue, two shapes.',
                    'Checkout takes MTN and Airtel Money, Visa and Mastercard, because a Ugandan customer who cannot pay by phone is a customer who does not buy.',
                    'Stock decrements on payment, not on adding to a basket, so two people cannot buy the last one.',
                    'Enquiries and customers land in a CRM rather than a phone, so a follow-up is a task someone owns instead of a message they may or may not remember.',
                    'The admin dashboard answers the money question directly: revenue this month, orders today, what is selling and what is not.',
                ],
                'stack' => ['Laravel', 'MySQL', 'Mobile Money', 'Visa/Mastercard', 'CRM', 'Admin dashboards'],
                'constraints' => [
                    'Payments have to be Ugandan. Card-only checkout excludes most of the market, so Mobile Money is a first-class path, not a fallback.',
                    'Two very different sales cycles. A water pump sells in three minutes; a house takes three months. The same system has to work sensibly for both.',
                    'Non-technical operators. The people adding listings are shop staff and agents, so the admin has to be obvious.',
                    'Public-facing. It is on the open internet with money moving through it, which sets the security floor.',
                ],
            ],

        ];
    }
}
