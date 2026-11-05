<?php

/*
| Classical AYUSH formulations and allied health products. Traditional medicines go by
| name and form, since strength varies by maker. Homoeopathic remedies list their usual
| potencies. Each line reads: name|strengths|form.
*/

$homeopathyPotencies = '6C,30C,200C,1M';

$homeopathyRemedies = [
    'Aconitum Napellus', 'Allium Cepa', 'Antimonium Tartaricum', 'Apis Mellifica', 'Argentum Nitricum', 'Arnica Montana', 'Arsenicum Album', 'Aurum Metallicum', 'Baryta Carbonica', 'Belladonna', 'Berberis Vulgaris', 'Bryonia Alba',
    'Calcarea Carbonica', 'Calcarea Fluorica', 'Calcarea Phosphorica', 'Cantharis', 'Carbo Vegetabilis', 'Causticum', 'Chamomilla', 'China Officinalis', 'Cimicifuga', 'Cocculus Indicus', 'Colocynthis', 'Conium Maculatum', 'Drosera', 'Dulcamara',
    'Eupatorium Perfoliatum', 'Euphrasia', 'Ferrum Phosphoricum', 'Gelsemium', 'Graphites', 'Hepar Sulphuris', 'Hypericum Perforatum', 'Ignatia Amara', 'Ipecacuanha', 'Kali Bichromicum', 'Kali Carbonicum', 'Kali Phosphoricum', 'Lachesis', 'Ledum Palustre', 'Lycopodium Clavatum',
    'Magnesia Phosphorica', 'Mercurius Solubilis', 'Natrum Muriaticum', 'Natrum Sulphuricum', 'Nitricum Acidum', 'Nux Vomica', 'Phosphorus', 'Phytolacca', 'Podophyllum', 'Pulsatilla', 'Rhus Toxicodendron', 'Ruta Graveolens', 'Sabal Serrulata', 'Sepia', 'Silicea', 'Spongia Tosta', 'Staphysagria', 'Sulphur', 'Symphytum', 'Thuja Occidentalis', 'Tuberculinum', 'Veratrum Album',
];

$motherTinctures = [
    'Alfalfa', 'Avena Sativa', 'Berberis Aquifolium', 'Calendula Officinalis', 'Carduus Marianus', 'Crataegus Oxyacantha', 'Echinacea', 'Ginkgo Biloba', 'Hydrastis Canadensis', 'Passiflora Incarnata', 'Rauwolfia Serpentina', 'Syzygium Jambolanum', 'Terminalia Arjuna', 'Thlaspi Bursa Pastoris', 'Withania Somnifera',
];

$tissueSalts = [
    'Calcarea Fluorica', 'Calcarea Phosphorica', 'Calcarea Sulphurica', 'Ferrum Phosphoricum', 'Kali Muriaticum', 'Kali Phosphoricum', 'Kali Sulphuricum', 'Magnesia Phosphorica', 'Natrum Muriaticum', 'Natrum Phosphoricum', 'Natrum Sulphuricum', 'Silicea',
];

return [
    'Ayurveda' => [
        'Ashwagandha Churna||Churna', 'Triphala Churna||Churna', 'Trikatu Churna||Churna', 'Sitopaladi Churna||Churna', 'Talisadi Churna||Churna', 'Hingvashtak Churna||Churna', 'Avipattikar Churna||Churna', 'Lavan Bhaskar Churna||Churna', 'Yashtimadhu Churna||Churna', 'Shatavari Churna||Churna', 'Brahmi Churna||Churna', 'Punarnava Churna||Churna', 'Haritaki Churna||Churna', 'Amalaki Churna||Churna', 'Guduchi Churna||Churna', 'Pushyanug Churna||Churna', 'Panchasakar Churna||Churna',
        'Triphala Guggulu||Tablet', 'Yograj Guggulu||Tablet', 'Mahayograj Guggulu||Tablet', 'Kaishore Guggulu||Tablet', 'Kanchanar Guggulu||Tablet', 'Gokshuradi Guggulu||Tablet', 'Sinhanad Guggulu||Tablet', 'Medohar Guggulu||Tablet', 'Punarnavadi Guggulu||Tablet', 'Lakshadi Guggulu||Tablet', 'Arogyavardhini Vati||Tablet', 'Chandraprabha Vati||Tablet', 'Sanjivani Vati||Tablet', 'Lashunadi Vati||Tablet', 'Khadiradi Vati||Tablet', 'Vyoshadi Vati||Tablet', 'Chitrakadi Vati||Tablet', 'Shankha Vati||Tablet', 'Agnitundi Vati||Tablet', 'Prabhakar Vati||Tablet', 'Giloy Ghan Vati||Tablet', 'Shilajit Vati||Tablet', 'Ashwagandha||Tablet', 'Neem||Capsule', 'Tulsi||Tablet', 'Gudmar||Tablet',
        'Dashmularishta||Arishta', 'Ashokarishta||Arishta', 'Arjunarishta||Arishta', 'Kumaryasava||Asava', 'Saraswatarishta||Arishta', 'Ashwagandharishta||Arishta', 'Abhayarishta||Arishta', 'Lohasava||Asava', 'Punarnavasava||Asava', 'Vasarishta||Arishta', 'Khadirarishta||Arishta', 'Sarivadyasava||Asava', 'Draksharishta||Arishta', 'Kutajarishta||Arishta', 'Amritarishta||Arishta', 'Pippalyasava||Asava', 'Usheerasava||Asava',
        'Chyawanprash||Avaleha', 'Vasavaleha||Avaleha', 'Agastya Haritaki Rasayana||Avaleha', 'Kushmanda Rasayana||Avaleha', 'Brahma Rasayana||Avaleha', 'Dashmula Kwath||Kwath', 'Maharasnadi Kwath||Kwath', 'Punarnavashtak Kwath||Kwath', 'Varunadi Kwath||Kwath', 'Pathyadi Kwath||Kwath', 'Rasnasaptak Kwath||Kwath', 'Sudarshan Ghan Vati||Tablet', 'Mahasudarshan Churna||Churna',
        'Brahmi Ghrita||Ghrita', 'Triphala Ghrita||Ghrita', 'Panchatikta Ghrita||Ghrita', 'Mahatikta Ghrita||Ghrita', 'Saraswata Ghrita||Ghrita', 'Mahanarayan Taila||Taila', 'Dhanwantaram Taila||Taila', 'Ksheerabala Taila||Taila', 'Bhringraj Taila||Taila', 'Neelibhringadi Taila||Taila', 'Anu Taila||Taila', 'Kottamchukkadi Taila||Taila', 'Jatyadi Taila||Taila', 'Pinda Taila||Taila',
        'Abhrak Bhasma||Bhasma', 'Godanti Bhasma||Bhasma', 'Praval Pishti||Pishti', 'Mukta Pishti||Pishti', 'Shankh Bhasma||Bhasma', 'Tamra Bhasma||Bhasma', 'Yashad Bhasma||Bhasma', 'Lauh Bhasma||Bhasma', 'Swarna Bhasma||Bhasma', 'Mahalakshmi Vilas Ras||Tablet', 'Smriti Sagar Ras||Tablet', 'Brihat Vata Chintamani Ras||Tablet', 'Tribhuvan Kirti Ras||Tablet', 'Punarnava Mandur||Tablet', 'Navayas Lauh||Tablet', 'Sootshekhar Ras||Tablet', 'Kamdudha Ras||Tablet',
    ],
    'Homeopathy' => array_merge(
        array_map(fn (string $remedy): string => "{$remedy}|{$homeopathyPotencies}|Dilution", $homeopathyRemedies),
        array_map(fn (string $remedy): string => "{$remedy} Mother Tincture|Q|Mother Tincture", $motherTinctures),
        array_map(fn (string $remedy): string => "{$remedy} Tissue Salt|3X,6X,12X,30X|Trituration Tablet", $tissueSalts),
    ),
    'Unani' => [
        'Habb-e-Asgand||Tablet', 'Habb-e-Hindi Zeeqi||Tablet', 'Habb-e-Jadwar||Tablet', 'Habb-e-Kabid Naushadri||Tablet', 'Habb-e-Mudir||Tablet', 'Habb-e-Shifa||Tablet', 'Habb-e-Suranjan||Tablet', 'Habb-e-Tinkar||Tablet', 'Habb-e-Papita||Tablet',
        'Jawarish Jalinoos||Jawarish', 'Jawarish Kamooni||Jawarish', 'Jawarish Shahi||Jawarish', 'Jawarish Mastagi||Jawarish', 'Jawarish Tamar Hindi||Jawarish', 'Jawarish Bisbasa||Jawarish', 'Itrifal Kishneezi||Itrifal', 'Itrifal Muqil||Itrifal', 'Itrifal Shahatra||Itrifal', 'Itrifal Ustukhuddus||Itrifal', 'Itrifal Zamani||Itrifal',
        'Khamira Abresham Sada||Khamira', 'Khamira Abresham Hakim Arshadwala||Khamira', 'Khamira Gaozaban Sada||Khamira', 'Khamira Gaozaban Ambari||Khamira', 'Khamira Marwareed||Khamira', 'Khamira Banafsha||Khamira', 'Khamira Sandal Sada||Khamira', 'Majoon Falasfa||Majoon', 'Majoon Dabeed-ul-Ward||Majoon', 'Majoon Muqawwi-e-Reham||Majoon', 'Majoon Jograj Gugal||Majoon', 'Majoon Suranjan||Majoon', 'Majoon Sang-e-Sarmahi||Majoon', 'Majoon Azaraqi||Majoon', 'Majoon Chobchini||Majoon', 'Dawa-ul-Kurkum Kabir||Majoon', 'Dawa-ul-Misk Motadil Sada||Majoon',
        'Sharbat Sadar||Sharbat', 'Sharbat Banafsha||Sharbat', 'Sharbat Ahmed Shah||Sharbat', 'Sharbat Unnab||Sharbat', 'Sharbat Zufa Murakkab||Sharbat', 'Sharbat Faulad||Sharbat', 'Sharbat Bazoori Motadil||Sharbat', 'Sharbat Anjabar||Sharbat', 'Sharbat Toot Siyah||Sharbat', 'Laooq Sapistan Khiyar Shamberi||Laooq', 'Laooq Katan||Laooq', 'Roghan Baboona||Roghan', 'Roghan Surkh||Roghan', 'Roghan Kaddu||Roghan', 'Roghan Badam Shirin||Roghan', 'Roghan Malkangni||Roghan', 'Qurs Mulaiyin||Tablet', 'Qurs Kushta Khabsul Hadeed||Tablet', 'Qurs Tabasheer||Tablet', 'Qurs Ziabetus Khas||Tablet', 'Safoof-e-Muhazzil||Safoof', 'Safoof-e-Mulaiyin||Safoof', 'Safoof-e-Sailan||Safoof', 'Zimad-e-Muhallil||Zimad', 'Marham Kafoor||Marham', 'Marham Ushaq||Marham', 'Arq Gulab||Arq', 'Arq Mako||Arq', 'Arq Badiyan||Arq', 'Arq Ajeeb||Arq',
    ],
    'Siddha' => [
        'Nilavembu Kudineer Chooranam||Chooranam', 'Kabasura Kudineer Chooranam||Chooranam', 'Thirikadugu Chooranam||Chooranam', 'Thiripala Chooranam||Chooranam', 'Amukkara Chooranam||Chooranam', 'Elathi Chooranam||Chooranam', 'Parangipattai Chooranam||Chooranam', 'Seenthil Chooranam||Chooranam', 'Aavarai Kudineer Chooranam||Chooranam', 'Vilvathi Chooranam||Chooranam', 'Nellikkai Chooranam||Chooranam', 'Kadukkai Chooranam||Chooranam', 'Vallarai Chooranam||Chooranam', 'Sivanar Amirtham||Chooranam', 'Thaalisaathi Chooranam||Chooranam',
        'Pinda Thailam||Thailam', 'Vathakesari Thailam||Thailam', 'Karappan Thailam||Thailam', 'Kayathirumeni Thailam||Thailam', 'Arakku Thailam||Thailam', 'Vishnu Chakkaram||Tablet', 'Brahmanandha Bairavam||Tablet', 'Chandamarutha Chenduram||Chenduram', 'Linga Chenduram||Chenduram', 'Ayabringaraja Karpam||Karpam', 'Muthu Parpam||Parpam', 'Pavala Parpam||Parpam', 'Sangu Parpam||Parpam', 'Nandukkal Parpam||Parpam', 'Silasathu Parpam||Parpam', 'Kungiliya Parpam||Parpam', 'Thalaga Parpam||Parpam', 'Velvanga Parpam||Parpam',
        'Adathodai Manappagu||Manappagu', 'Madulai Manappagu||Manappagu', 'Thuthuvalai Nei||Nei', 'Vallarai Nei||Nei', 'Brammi Nei||Nei', 'Amukkara Lehyam||Lehyam', 'Inji Lehyam||Lehyam', 'Thetrankottai Lehyam||Lehyam', 'Kumari Lehyam||Lehyam', 'Karisalai Lehyam||Lehyam', 'Thiribala Mathirai||Tablet', 'Bavana Kadukkai||Tablet', 'Kaiyan Thylam||Thailam', 'Mathan Thailam||Thailam',
    ],
    'Yoga & Naturopathy' => [
        'Wheatgrass Powder||Powder', 'Spirulina|500 mg|Tablet', 'Amla Juice||Juice', 'Aloe Vera Juice||Juice', 'Neem Capsule|250 mg|Capsule', 'Turmeric (Curcumin)|500 mg|Capsule', 'Flaxseed Powder||Powder', 'Isabgol Husk||Powder', 'Giloy Juice||Juice', 'Ginger Powder||Powder', 'Fenugreek Seed Powder||Powder', 'Moringa Powder||Powder', 'Honey (Raw)||Syrup', 'Epsom Salt (Bath)||Powder', 'Mud Pack (Multani Mitti)||Powder', 'Coconut Oil (Virgin)||Oil', 'Sesame Oil (Massage)||Oil',
    ],
    'Physiotherapist' => [
        'Diclofenac + Menthol Gel|1% + 5%|Gel', 'Methyl Salicylate + Menthol Ointment|10% + 3%|Ointment', 'Capsaicin Cream|0.025%,0.075%|Cream', 'Ultrasound Therapy Gel||Gel', 'Etofenamate Gel|10%|Gel', 'Hot Fomentation Pack||Pack', 'Cold Spray (Ethyl Chloride)||Spray',
    ],
    'Dietitian' => [
        'Whey Protein Powder||Powder', 'Soy Protein Powder||Powder', 'Diabetic Nutrition Powder||Powder', 'Renal Nutrition Powder||Powder', 'Pregnancy Nutrition Powder||Powder', 'Omega-3 Fatty Acids|1000 mg|Capsule', 'Vitamin E|200 mg,400 mg|Capsule', 'Biotin|5 mg,10 mg|Tablet', 'L-Carnitine|500 mg|Tablet', 'Inositol + Folic Acid|2 g + 200 mcg|Sachet', 'Medium Chain Triglyceride Oil||Oil', 'Dietary Fibre Powder||Powder', 'Magnesium|250 mg|Tablet', 'Selenium|100 mcg|Tablet', 'Coenzyme Q10|100 mg|Capsule',
    ],
];
