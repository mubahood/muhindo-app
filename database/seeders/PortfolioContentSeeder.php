<?php

namespace Database\Seeders;

use App\Models\Education;
use App\Models\Experience;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Skill;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Seeds the real portfolio content (identity, about, stats, projects, skills,
 * experience, education, research, products, languages) sourced from
 * amout-muhindo.md / config/portfolio.php in the muhindomubaraka project.
 * Safe to re-run. Every write is an upsert keyed on a natural identifier.
 */
class PortfolioContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedServices();
        $this->seedProjects();
        // The long-form half, kept in its own file because prose is edited
        // far more often than a slug or a sort order.
        $this->call(CaseStudySeeder::class);
        $this->seedSkills();
        $this->seedExperience();
        $this->seedEducation();

        $this->command->info('PortfolioContentSeeder: identity/about/stats/research/products/languages + '
            .PortfolioProject::count().' projects, '.Skill::count().' skills, '
            .Experience::count().' experience entries, '.Education::count().' education entries.');
    }

    private function seedSettings(): void
    {
        Settings::set('portfolio.identity', json_encode([
            'name' => 'Muhindo Mubaraka',
            'title' => 'Full-Stack Developer & Software Engineer',
            // The headline claims the work, not a map. What makes someone hire or
            // enrol is that the software gets finished and the teaching lands,
            // neither of which is a regional trait, and naming a region up front
            // quietly caps the audience at it.
            //
            // "scales" over the earlier "ships": school directors and ministry
            // officers read this line too, and "ships" is a word that only means
            // anything to programmers. "...teach others to do the same" carries both
            // the building and the standard without repeating the verb.
            'tagline' => 'I build software that works, and I teach others to do the same.',
            'location' => 'Kampala, Uganda',
            'initials' => 'MM',
        ]));

        Settings::set('portfolio.contact', json_encode([
            'emails' => ['mubahood360@gmail.com', 'muhindo@8technologies.net'],
            'phones' => ['+256 783 204 665', '+256 706 638 484'],
            'github' => 'https://github.com/mubahood',
            'github_label' => 'github.com/mubahood',
            'youtube' => 'https://www.youtube.com/@LearnItWithMuhindo',
            'youtube_label' => 'Learn It With Muhindo',
            'learning_site' => 'https://learnitwithmuhindo.com',
            'learning_label' => 'learnitwithmuhindo.com',
        ]));

        Settings::set('portfolio.stats', json_encode([
            ['value' => '9+', 'label' => 'Years building systems'],
            ['value' => '20+', 'label' => 'Systems in production'],
            ['value' => '23K', 'label' => 'YouTube subscribers'],
            ['value' => '200+', 'label' => 'Free tutorials published'],
        ]));

        Settings::set('portfolio.about', json_encode([
            'heading' => 'Nine years of building systems people actually use',
            'lead' => 'I design and build software for anyone with a real problem, and I teach others to do the same.',
            'paragraphs' => [
                'I started writing code in secondary school, mostly to understand how things worked. Nine years later, I am still doing that. Except now the systems I build are running inside government ministries, clinics, schools and NGOs across Uganda. I own the whole journey: the first conversation about what the problem actually is, through architecture, development, deployment, and the staff training that decides whether any of it is still running a year later.',
                'My day-to-day work sits at the intersection of full-stack engineering, database administration and server infrastructure. I am comfortable at any layer: writing the SQL, configuring the server, building the mobile app, and sitting with a district officer to understand why the data looks the way it does. I am currently finishing a Master of Science in Computer Science at Makerere University, with research focused on distributed machine learning and blockchain for agricultural early-warning systems.',
                'Alongside delivery work, I run a YouTube channel where I teach web and mobile development in plain language, and I built the e-learning platform on this site so students can take structured courses at their own pace. The teaching and the building feed each other. I explain things better because I still do them, and I build things better because I have to explain them.',
            ],
            'home' => [
                'I started writing code in secondary school, mostly to understand how things worked. Nine years later I am still doing that. Except now the systems I build are running inside government ministries, clinics, schools and farms across Uganda.',
                'I own the whole journey: the first conversation about what the problem really is, through architecture, development, deployment, and the staff training that decides whether any of it is still running a year later.',
            ],
        ]));

        Settings::set('courses.faq', json_encode([
            ['q' => 'How do I pay?', 'a' => 'Pay with MTN Mobile Money, Airtel Money, or a Visa/Mastercard card, all handled securely through Flutterwave. Free courses need no payment at all.'],
            ['q' => 'Is it self-paced?', 'a' => "Yes, you learn on your own schedule. There are no live class times to catch. Lessons stay available for the course's access window."],
            ['q' => 'Do I get a certificate?', 'a' => 'Yes, a certificate is issued automatically once you complete every lesson in the course, and you can verify it any time from its own link.'],
            ['q' => 'What if I get stuck?', 'a' => "Use the course's discussion Q&A to ask a question. I read and answer these myself."],
        ]));

        // Names only. The home page renders a logo when one exists at
        // public/images/clients/{slug}.png and falls back to the wordmark until
        // then, so this list never has to wait on artwork.
        Settings::set('portfolio.clients', json_encode([
            'Ministry of Agriculture', 'Uganda Wildlife Authority', 'Uganda Communications Commission',
            'NUDIPU', 'CEHURD', 'Makerere University', 'Eight Tech Consults',
        ]));

        /*
         * Testimonials are seeded EMPTY on purpose.
         *
         * Every other seed here is a real fact taken from the owner's CV. A
         * quote is different: it is attributed to a named person, and inventing
         * one (even as filler meant to be replaced) publishes words that
         * person never said. The home page simply omits the section until real
         * ones are added, and the admin editor accepts them in this shape:
         *   quote, name, role, org, photo (path under public/), link
         */
        if (Settings::get('portfolio.testimonials') === null) {
            Settings::set('portfolio.testimonials', json_encode([]));
        }

        Settings::set('portfolio.research', json_encode([
            'title' => 'Blockchain-Secured Federated Learning for Livestock Disease Early Warning',
            'institution' => 'MSc Computer Science, Makerere University',
            'supervisor' => 'Supervisor: Dr. Chongomweru Halimu',
            'body' => "My Master's research targets foot-and-mouth disease (FMD) detection in Ugandan cattle farms. It combines distributed machine learning (so farm data never has to leave the source) with blockchain for trust and tamper-resistance, designed to be lightweight enough to deploy in rural environments with limited connectivity.",
            'outcomes' => ['An open-source prototype', 'Peer-reviewed publications', 'A deployable early-warning dashboard'],
            'areas' => ['Distributed systems', 'Database systems', 'Network security', 'Machine learning'],
        ]));

        Settings::set('portfolio.products', json_encode([
            [
                'name' => 'LugaFlix', 'icon' => 'fa-film',
                'tagline' => 'Luganda-language movie streaming for Android: phones, tablets and TV.',
                'body' => 'Built for everyday Ugandan users around local-language entertainment that is easy to access via Google Play. Features the VJ experience, Matatu, the Connect module and a Games Arcade.',
                'link' => null,
            ],
            [
                'name' => 'CodeCanyon Templates', 'icon' => 'fa-code',
                'tagline' => 'Premium HTML & React templates and code products.',
                'body' => 'Polished, production-ready templates sold on CodeCanyon as an ongoing product revenue stream.',
                'link' => null,
            ],
            [
                'name' => 'Learn It With Muhindo', 'icon' => 'fa-graduation-cap',
                'tagline' => '23,000 subscribers · 200+ free tutorials.',
                'body' => 'A YouTube channel teaching web and mobile development in plain language, plus structured courses on this site. Free to browse, paid if you want the full thing.',
                'link' => 'https://learnitwithmuhindo.com',
            ],
        ]));

        Settings::set('portfolio.languages', json_encode([
            ['name' => 'English', 'level' => 'C1 (Proficient)'],
            ['name' => 'Luganda', 'level' => 'C1 (Proficient)'],
            ['name' => 'Swahili', 'level' => 'C1 (Proficient)'],
            ['name' => 'Lukonzo', 'level' => 'Native'],
        ]));
    }

    private function seedServices(): void
    {
        $rows = [
            ['icon' => 'fa-cubes', 'title' => 'Enterprise system design', 'description' => 'Architecting and building multi-platform information systems (web, Android, iOS, USSD/SMS) that run reliably at national scale and work offline in the field.'],
            ['icon' => 'fa-database', 'title' => 'Databases & data governance', 'description' => 'Schema design, query optimisation, migration, backup and recovery across MySQL, PostgreSQL and MongoDB, with data governance built in from the start.'],
            ['icon' => 'fa-shield-halved', 'title' => 'Infrastructure & security', 'description' => 'Linux server administration, Docker, Nginx, cloud deployment, and cybersecurity best practice: SSL/TLS, RBAC, encryption and secure APIs.'],
            ['icon' => 'fa-diagram-project', 'title' => 'ICT strategy & delivery', 'description' => 'Digital transformation strategy, IT policy, vendor and stakeholder management, and end-to-end project delivery from requirements to rollout.'],
            ['icon' => 'fa-plug', 'title' => 'Integration & interoperability', 'description' => 'Payment gateways, GIS/GPS, biometrics, barcode/QR and USSD/SMS. I integrate systems so government and private data move together without manual re-entry.'],
            ['icon' => 'fa-people-group', 'title' => 'Training & capacity building', 'description' => 'Hands-on staff training and user enablement across districts, plus technical documentation and ongoing support that makes systems stick after handover.'],
        ];

        foreach ($rows as $i => $r) {
            Service::updateOrCreate(['title' => $r['title']], $r + ['sort_order' => $i]);
        }
    }

    private function seedProjects(): void
    {
        $rows = [
            ['slug' => 'ulits', 'title' => 'Uganda Livestock Information Tracking System (ULITS)', 'external_link' => 'https://u-lits.com',
                'description' => 'National multi-platform information system for livestock registration, movement tracking, vaccination records, and disease surveillance. Built for the Ministry of Agriculture.',
                'tags' => ['Web', 'Android', 'iOS', 'Offline-first', 'GIS/GPS', 'MySQL', 'SMS/USSD'],
                'highlights' => ['Offline-first architecture for use in low-connectivity rural districts.', 'GPS/GIS integration for movement tracking and disease surveillance.', 'Role-based access control across web, Android and iOS clients.', 'SMS/USSD notifications and MySQL database administration.', 'User training conducted across multiple districts.'],
                'featured' => true],
            ['slug' => 'school-dynamics', 'title' => 'School Dynamics: School Management Information System', 'external_link' => 'https://schooldynamics.ug',
                'description' => 'SaaS platform serving primary and secondary schools with role-based portals for parents, teachers, and administrators.',
                'tags' => ['SaaS', 'Mobile Money', 'Visa', 'SMS/Email', 'Dashboards'],
                'highlights' => ['Modules for student records, staff, fee collection, examinations and timetabling.', 'Library, transport and hostel management.', 'Role-based portals for parents, teachers and administrators.', 'Mobile Money / Visa payment gateways and SMS/email notifications.', 'Reporting dashboards for school administrators.'],
                'featured' => true],
            ['slug' => 'hospital-management', 'title' => 'Hospital Management System', 'external_link' => 'https://globalhealthrescue.com',
                'description' => 'End-to-end healthcare information system covering patient records, appointments, laboratory, pharmacy, billing and insurance claims.',
                'tags' => ['EHR', 'Pharmacy', 'Billing', 'Insurance', 'RBAC'],
                'highlights' => ['Electronic health records (EHR) and appointment scheduling.', 'Laboratory, pharmacy, billing and insurance claims modules.', 'Role-based portals for medical staff and administrators.', 'Strong data security measures throughout.'],
                'featured' => true],
            ['slug' => 'wildlife-offenders', 'title' => 'Wildlife Offenders Database', 'external_link' => null,
                'description' => 'Web dashboard and mobile app for wildlife crime data capture, offender tracking, case management and enforcement analytics, built for the Uganda Wildlife Authority.',
                'tags' => ['Web', 'Mobile', 'Biometrics', 'Case management', 'Analytics'],
                'highlights' => ['Wildlife crime data capture and offender tracking.', 'Case management workflows with biometric integration.', 'Enforcement analytics for the Uganda Wildlife Authority.'],
                'featured' => false],
            ['slug' => 'seed-tracking', 'title' => 'National Seed Tracking & Tracing System', 'external_link' => null,
                'description' => 'Digital platform to monitor seed distribution and quality control with barcode/QR field verification and voucher management, for the Ministry of Agriculture.',
                'tags' => ['Barcode/QR', 'Vouchers', 'Interoperability', 'Field verification'],
                'highlights' => ['Barcode/QR scanning for field verification of seed.', 'Voucher management and quality control monitoring.', 'Interoperable data exchange with government systems.'],
                'featured' => false],
            ['slug' => 'pwd-observatory', 'title' => 'ICT for Persons with Disabilities Observatory', 'external_link' => null,
                'description' => 'Nationwide database and resource platform with analytics dashboards for disability-inclusive policy formulation, with the Uganda Communications Commission and NUDIPU.',
                'tags' => ['Database', 'Analytics', 'Policy', 'National'],
                'highlights' => ['Nationwide database and resource platform.', 'Data analytics dashboards for policy formulation.', 'Built with the Uganda Communications Commission and NUDIPU.'],
                'featured' => false],
            ['slug' => 'human-rights-reporting', 'title' => 'Human Rights Reporting System', 'external_link' => null,
                'description' => 'Secure case documentation system with evidence capture, encrypted storage, case-tracking workflows and trend analysis, built for CEHURD.',
                'tags' => ['Encryption', 'Case tracking', 'Evidence capture', 'Dashboards'],
                'highlights' => ['Evidence capture with encrypted data storage.', 'Case-tracking workflows for human rights documentation.', 'Trend-analysis dashboards for CEHURD.'],
                'featured' => false],
            ['slug' => 'ecommerce-realestate', 'title' => 'E-Commerce & Real Estate Platforms', 'external_link' => 'https://afriinventions.com',
                'description' => 'Scalable e-commerce platform and a real estate management system with inventory, payments, CRM and admin dashboards.',
                'tags' => ['E-commerce', 'CRM', 'Payments', 'Inventory'],
                'highlights' => ['Scalable e-commerce platform (afriinventions.com).', 'Real estate management with inventory and payment integration.', 'CRM and administrative dashboards.'],
                'featured' => false],
        ];

        foreach ($rows as $i => $r) {
            PortfolioProject::updateOrCreate(['slug' => $r['slug']], [
                'title' => $r['title'],
                'description' => $r['description'],
                'tags' => $r['tags'],
                'highlights' => $r['highlights'],
                'external_link' => $r['external_link'],
                'is_featured' => $r['featured'],
                'sort_order' => $i,
            ]);
        }
    }

    private function seedSkills(): void
    {
        $groups = [
            'Systems & Infrastructure' => ['Linux/Unix server administration', 'Docker', 'Nginx', 'Apache', 'AWS', 'Firebase', 'DigitalOcean', 'Network configuration'],
            'Database Administration' => ['MySQL', 'PostgreSQL', 'MongoDB', 'Firebase', 'SQLite', 'Schema design', 'Query optimisation', 'Backup & recovery', 'Data migration'],
            'Programming Languages' => ['Python', 'PHP', 'JavaScript', 'Dart', 'Java', 'Kotlin', 'C#', 'SQL'],
            'Backend Frameworks' => ['Laravel (Expert)', 'Django', 'CodeIgniter', 'ASP.NET', 'RESTful API design'],
            'Frontend & Mobile' => ['React.js', 'Vue.js', 'Bootstrap', 'Tailwind CSS', 'Flutter/Dart (Expert)', 'Android (Java/Kotlin)', 'iOS'],
            'IS / ERP Platforms' => ['Student information systems', 'School management', 'E-learning', 'Hospital IS', 'Livestock traceability'],
            'Security' => ['SSL/TLS', 'JWT', 'Role-based access control', 'Data encryption', 'Secure API development'],
            'DevOps & Integration' => ['Docker', 'Git/GitHub', 'CI/CD', 'USSD/SMS gateways', 'GIS/GPS', 'Payment gateways', 'Barcode/QR', 'Biometrics'],
            'Project Management' => ['Agile/Scrum', 'Stakeholder engagement', 'User training', 'Technical documentation'],
            'Productivity & Design' => ['MS Office Suite', 'Adobe Illustrator/Photoshop', 'Figma', 'WordPress', 'LaTeX'],
        ];

        $order = 0;
        foreach ($groups as $category => $items) {
            foreach ($items as $name) {
                Skill::updateOrCreate(['name' => $name, 'category' => $category], ['sort_order' => $order++]);
            }
        }
    }

    private function seedExperience(): void
    {
        $rows = [
            ['company' => 'Eight Tech Consults Ltd', 'role' => 'Full-Stack Developer & Application Engineer', 'start_date' => '2021-01-01', 'end_date' => null,
                'description' => 'Lead technical architect on complex enterprise information systems for government agencies, NGOs and private clients. Responsible for the full software development lifecycle: requirements, architecture, development, testing, deployment, training and ongoing support.'],
            ['company' => 'M-Omulimisa Uganda Limited', 'role' => 'Lead Developer, Agrihub Mobile Application', 'start_date' => '2023-01-01', 'end_date' => '2024-12-31',
                'description' => 'Led development of an agricultural information platform integrating weather APIs, marketplace functionality and mobile payment systems. Conducted user training across multiple districts.'],
            ['company' => 'Gonevas.com', 'role' => 'Full-Stack Developer', 'start_date' => '2019-01-01', 'end_date' => '2021-12-31',
                'description' => 'E-commerce web and Android app development, hosting and content management.'],
            ['company' => 'Independent (Freelance)', 'role' => 'Freelance Web Developer', 'start_date' => '2018-01-01', 'end_date' => '2021-12-31',
                'description' => 'Websites and systems for IUIU Alumni Association, Double Tree Tours, Lukman Primary School and UgNews24.'],
            ['company' => 'Learn It With Muhindo (YouTube)', 'role' => 'Programming Instructor', 'start_date' => '2018-01-01', 'end_date' => '2022-12-31',
                'description' => 'Taught web and mobile development through free video tutorials. The channel grew to around 23,000 subscribers. Content covered HTML, CSS, JavaScript, PHP, Laravel, Flutter and Android development in plain, practical language.'],
        ];

        foreach ($rows as $i => $r) {
            Experience::updateOrCreate(['company' => $r['company'], 'role' => $r['role']], $r + ['sort_order' => $i]);
        }
    }

    private function seedEducation(): void
    {
        $rows = [
            ['institution' => 'Makerere University, Kampala', 'degree' => 'MSc Computer Science (in progress)', 'field' => 'Distributed Systems', 'start_date' => '2024-01-01', 'end_date' => null,
                'description' => 'Research: blockchain-based federated learning. Distributed systems, databases, network security, ML.'],
            ['institution' => 'Islamic University of Technology (IUT), Dhaka', 'degree' => 'BSc Computer Science and Engineering', 'field' => 'Software Engineering', 'start_date' => '2019-01-01', 'end_date' => '2020-12-31',
                'description' => 'Second Class Upper. Software Engineering & Technical Education.'],
            ['institution' => 'Islamic University of Technology (IUT), Dhaka', 'degree' => 'Higher Diploma, Computer Science and Engineering', 'field' => 'Web Technologies', 'start_date' => '2016-01-01', 'end_date' => '2018-12-31',
                'description' => 'Second Class Upper. Web Technologies & Systems Development.'],
            ['institution' => 'Rwenzori Saad Islamic Institute, Kasese', 'degree' => 'Uganda Advanced Certificate of Education', 'field' => null, 'start_date' => '2014-01-01', 'end_date' => '2015-12-31',
                'description' => 'Physics, Mathematics, Computer Studies, Entrepreneurship.'],
        ];

        foreach ($rows as $i => $r) {
            Education::updateOrCreate(['institution' => $r['institution'], 'degree' => $r['degree']], $r + ['sort_order' => $i]);
        }
    }
}
