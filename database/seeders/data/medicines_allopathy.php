<?php

/*
| Generic medicines by the specialty that most often prescribes them. A medicine used by
| several specialties appears once. Each line reads: generic name|strengths|form.
| No brand names, and no combinations India has banned.
*/
return [
    'General Physician' => [
        'Paracetamol|500 mg,650 mg|Tablet', 'Paracetamol|125 mg/5 ml,250 mg/5 ml|Syrup', 'Paracetamol|1 g/100 ml|Infusion',
        'Ibuprofen|200 mg,400 mg,600 mg|Tablet', 'Ibuprofen + Paracetamol|400 mg + 325 mg|Tablet', 'Diclofenac|50 mg|Tablet', 'Diclofenac|75 mg/3 ml|Injection',
        'Aceclofenac|100 mg|Tablet', 'Aceclofenac + Paracetamol|100 mg + 325 mg|Tablet', 'Mefenamic Acid|250 mg,500 mg|Tablet', 'Naproxen|250 mg,500 mg|Tablet', 'Tramadol|50 mg|Capsule',
        'Amoxicillin|250 mg,500 mg|Capsule', 'Amoxicillin + Clavulanic Acid|500 mg + 125 mg,875 mg + 125 mg|Tablet', 'Amoxicillin + Clavulanic Acid|200 mg + 28.5 mg/5 ml|Syrup',
        'Azithromycin|250 mg,500 mg|Tablet', 'Clarithromycin|250 mg,500 mg|Tablet', 'Doxycycline|100 mg|Capsule', 'Cefixime|200 mg,400 mg|Tablet', 'Cefuroxime|250 mg,500 mg|Tablet',
        'Cefpodoxime|100 mg,200 mg|Tablet', 'Cefadroxil|500 mg|Capsule', 'Cephalexin|250 mg,500 mg|Capsule', 'Ceftriaxone|500 mg,1 g|Injection', 'Ciprofloxacin|250 mg,500 mg|Tablet',
        'Ofloxacin|200 mg,400 mg|Tablet', 'Levofloxacin|250 mg,500 mg,750 mg|Tablet', 'Moxifloxacin|400 mg|Tablet', 'Nitrofurantoin|100 mg|Capsule', 'Cotrimoxazole|400 mg + 80 mg,800 mg + 160 mg|Tablet',
        'Metronidazole|200 mg,400 mg|Tablet', 'Tinidazole|500 mg|Tablet', 'Albendazole|400 mg|Tablet', 'Ivermectin|6 mg,12 mg|Tablet', 'Fluconazole|150 mg|Tablet', 'Acyclovir|200 mg,400 mg,800 mg|Tablet',
        'Artemether + Lumefantrine|80 mg + 480 mg|Tablet', 'Artesunate|50 mg|Tablet', 'Chloroquine|250 mg|Tablet', 'Primaquine|7.5 mg,15 mg|Tablet', 'Oseltamivir|75 mg|Capsule',
        'Cetirizine|10 mg|Tablet', 'Levocetirizine|5 mg|Tablet', 'Fexofenadine|120 mg,180 mg|Tablet', 'Loratadine|10 mg|Tablet', 'Chlorpheniramine|4 mg|Tablet', 'Promethazine|10 mg,25 mg|Tablet',
        'Metformin|500 mg,850 mg,1000 mg|Tablet', 'Glimepiride|1 mg,2 mg,3 mg,4 mg|Tablet', 'Amlodipine|2.5 mg,5 mg,10 mg|Tablet', 'Telmisartan|20 mg,40 mg,80 mg|Tablet', 'Atorvastatin|10 mg,20 mg,40 mg,80 mg|Tablet',
        'Pantoprazole|20 mg,40 mg|Tablet', 'Omeprazole|20 mg|Capsule', 'Domperidone|10 mg|Tablet', 'Ondansetron|4 mg,8 mg|Tablet', 'Levothyroxine|25 mcg,50 mcg,75 mcg,100 mcg|Tablet',
        'Prednisolone|5 mg,10 mg,20 mg|Tablet', 'Dexamethasone|0.5 mg,4 mg|Tablet', 'Dexamethasone|4 mg/ml|Injection', 'Hydrocortisone|100 mg|Injection',
        'ORS||Sachet', 'Normal Saline|500 ml|Infusion', 'Ringer Lactate|500 ml|Infusion', 'Dextrose|5% 500 ml|Infusion', 'Vitamin B Complex||Tablet', 'Vitamin C|500 mg|Tablet',
        'Vitamin D3|1000 IU,60000 IU|Capsule', 'Multivitamin||Tablet', 'Zinc Sulphate|20 mg,50 mg|Tablet', 'Calcium Carbonate|500 mg|Tablet', 'Calcium + Vitamin D3|500 mg + 250 IU|Tablet',
        'Ferrous Ascorbate|100 mg|Tablet', 'Ferrous Sulphate + Folic Acid|100 mg + 0.5 mg|Tablet', 'Folic Acid|5 mg|Tablet', 'Methylcobalamin|500 mcg,1500 mcg|Tablet', 'Dextromethorphan|30 mg/5 ml|Syrup', 'Ambroxol|30 mg|Tablet', 'Guaifenesin|100 mg/5 ml|Syrup',
    ],
    'Dentist' => [
        'Chlorhexidine Mouthwash|0.2%|Mouthwash', 'Povidone Iodine Gargle|2%|Gargle', 'Benzydamine Mouthwash|0.15%|Mouthwash', 'Lidocaine Oral Gel|2%|Gel', 'Lidocaine + Adrenaline|2% + 1:80000|Injection',
        'Articaine + Adrenaline|4% + 1:100000|Injection', 'Benzocaine Oral Gel|20%|Gel', 'Choline Salicylate + Benzalkonium|8.7% + 0.01%|Gel', 'Triamcinolone Oral Paste|0.1%|Paste', 'Sodium Fluoride|0.05%|Mouthwash',
        'Sodium Fluoride Varnish|5%|Varnish', 'Potassium Nitrate Toothpaste|5%|Paste', 'Clotrimazole Mouth Paint|1%|Solution', 'Ketorolac|10 mg|Tablet', 'Clindamycin|150 mg,300 mg|Capsule', 'Metronidazole Gel (Dental)|1%|Gel',
    ],
    'Gynecologist' => [
        'Progesterone (Natural Micronised)|100 mg,200 mg,300 mg|Capsule', 'Dydrogesterone|10 mg|Tablet', 'Norethisterone|5 mg|Tablet', 'Medroxyprogesterone|2.5 mg,5 mg,10 mg|Tablet', 'Medroxyprogesterone|150 mg/ml|Injection',
        'Estradiol Valerate|1 mg,2 mg|Tablet', 'Clomiphene Citrate|25 mg,50 mg,100 mg|Tablet', 'Letrozole|2.5 mg,5 mg|Tablet', 'Cabergoline|0.25 mg,0.5 mg|Tablet', 'Bromocriptine|2.5 mg|Tablet',
        'Levonorgestrel|0.75 mg,1.5 mg|Tablet', 'Ethinylestradiol + Levonorgestrel|0.03 mg + 0.15 mg|Tablet', 'Ethinylestradiol + Desogestrel|0.03 mg + 0.15 mg|Tablet', 'Ethinylestradiol + Drospirenone|0.03 mg + 3 mg|Tablet', 'Tranexamic Acid|500 mg|Tablet',
        'Mefenamic Acid + Tranexamic Acid|250 mg + 500 mg|Tablet', 'Dienogest|2 mg|Tablet', 'Clotrimazole Vaginal|100 mg,200 mg,500 mg|Pessary', 'Metronidazole Vaginal|500 mg|Pessary', 'Doxylamine + Pyridoxine|10 mg + 10 mg|Tablet',
        'Oxytocin|5 IU/ml|Injection', 'Misoprostol|200 mcg|Tablet', 'Mifepristone|200 mg|Tablet', 'Methylergometrine|0.125 mg|Tablet', 'Labetalol|100 mg,200 mg|Tablet', 'Nifedipine|10 mg,20 mg|Tablet', 'Magnesium Sulphate|50% w/v|Injection', 'Tetanus Toxoid|0.5 ml|Injection', 'Anti-D Immunoglobulin|300 mcg|Injection',
    ],
    'Pediatrician' => [
        'Paracetamol Drops|100 mg/ml|Drops', 'Ibuprofen Suspension|100 mg/5 ml|Syrup', 'Amoxicillin Suspension|125 mg/5 ml,250 mg/5 ml|Syrup', 'Azithromycin Suspension|100 mg/5 ml,200 mg/5 ml|Syrup', 'Cefixime Suspension|50 mg/5 ml,100 mg/5 ml|Syrup',
        'Cefpodoxime Suspension|50 mg/5 ml|Syrup', 'Albendazole Suspension|200 mg/5 ml|Syrup', 'Zinc Syrup|20 mg/5 ml|Syrup', 'Iron Drops|25 mg/ml|Drops', 'Vitamin D3 Drops|400 IU/ml,800 IU/ml|Drops', 'Multivitamin Drops||Drops',
        'Salbutamol Syrup|2 mg/5 ml|Syrup', 'Levosalbutamol Syrup|1 mg/5 ml|Syrup', 'Cetirizine Syrup|5 mg/5 ml|Syrup', 'Ondansetron Syrup|2 mg/5 ml|Syrup', 'Domperidone Drops|10 mg/ml|Drops', 'Lactobacillus Sachet||Sachet', 'Saccharomyces Boulardii|250 mg|Sachet', 'Saline Nasal Drops|0.65%|Drops',
        'BCG Vaccine||Vaccine', 'Hepatitis B Vaccine||Vaccine', 'Oral Polio Vaccine||Vaccine', 'Pentavalent Vaccine||Vaccine', 'Rotavirus Vaccine||Vaccine', 'Measles Mumps Rubella Vaccine||Vaccine', 'Pneumococcal Conjugate Vaccine||Vaccine', 'Typhoid Conjugate Vaccine||Vaccine', 'Varicella Vaccine||Vaccine', 'Hepatitis A Vaccine||Vaccine', 'Influenza Vaccine||Vaccine', 'Tdap Vaccine||Vaccine', 'HPV Vaccine||Vaccine',
    ],
    'Dermatologist' => [
        'Clotrimazole Cream|1%|Cream', 'Terbinafine Cream|1%|Cream', 'Terbinafine|250 mg|Tablet', 'Itraconazole|100 mg,200 mg|Capsule', 'Griseofulvin|250 mg,500 mg|Tablet', 'Ketoconazole Shampoo|2%|Shampoo', 'Luliconazole Cream|1%|Cream', 'Sertaconazole Cream|2%|Cream',
        'Permethrin Cream|5%|Cream', 'Mupirocin Ointment|2%|Ointment', 'Fusidic Acid Cream|2%|Cream', 'Clobetasol Cream|0.05%|Cream', 'Betamethasone Cream|0.05%,0.1%|Cream', 'Mometasone Cream|0.1%|Cream', 'Hydrocortisone Cream|1%|Cream', 'Fluticasone Cream|0.05%|Cream',
        'Tacrolimus Ointment|0.03%,0.1%|Ointment', 'Adapalene Gel|0.1%|Gel', 'Benzoyl Peroxide Gel|2.5%,5%|Gel', 'Clindamycin Gel|1%|Gel', 'Tretinoin Cream|0.025%,0.05%|Cream', 'Isotretinoin|10 mg,20 mg|Capsule', 'Azelaic Acid Cream|10%,20%|Cream', 'Salicylic Acid Ointment|3%,6%|Ointment',
        'Hydroquinone Cream|2%|Cream', 'Minoxidil Solution|2%,5%|Solution', 'Finasteride|1 mg|Tablet', 'Calamine Lotion|8%|Lotion', 'Liquid Paraffin + White Soft Paraffin||Cream', 'Urea Cream|10%,20%|Cream', 'Methotrexate|2.5 mg,7.5 mg,10 mg|Tablet', 'Hydroxyzine|10 mg,25 mg|Tablet', 'Sunscreen SPF 50||Lotion', 'Acitretin|10 mg,25 mg|Capsule',
    ],
    'Orthopedic' => [
        'Etoricoxib|60 mg,90 mg,120 mg|Tablet', 'Celecoxib|100 mg,200 mg|Capsule', 'Thiocolchicoside|4 mg,8 mg|Tablet', 'Chlorzoxazone|500 mg|Tablet', 'Tizanidine|2 mg,4 mg|Tablet', 'Baclofen|10 mg,20 mg|Tablet', 'Glucosamine|500 mg,750 mg|Tablet', 'Glucosamine + Chondroitin|500 mg + 400 mg|Tablet',
        'Diacerein|50 mg|Capsule', 'Alendronate|35 mg,70 mg|Tablet', 'Risedronate|35 mg|Tablet', 'Zoledronic Acid|5 mg/100 ml|Infusion', 'Teriparatide|20 mcg|Injection', 'Calcitriol|0.25 mcg|Capsule', 'Diclofenac Gel|1%|Gel', 'Methylprednisolone|40 mg/ml|Injection', 'Hyaluronic Acid (Intra-articular)|20 mg/2 ml|Injection', 'Enoxaparin|40 mg/0.4 ml|Injection', 'Pregabalin|75 mg,150 mg|Capsule',
    ],
    'ENT' => [
        'Xylometazoline Nasal Drops|0.05%,0.1%|Drops', 'Oxymetazoline Nasal Spray|0.05%|Spray', 'Fluticasone Nasal Spray|50 mcg|Spray', 'Mometasone Nasal Spray|50 mcg|Spray', 'Budesonide Nasal Spray|64 mcg|Spray', 'Azelastine Nasal Spray|140 mcg|Spray', 'Saline Nasal Spray|0.9%|Spray',
        'Ciprofloxacin Ear Drops|0.3%|Drops', 'Ofloxacin Ear Drops|0.3%|Drops', 'Clotrimazole Ear Drops|1%|Drops', 'Wax Softening Ear Drops (Paradichlorobenzene)||Drops', 'Betahistine|8 mg,16 mg,24 mg|Tablet', 'Cinnarizine|25 mg|Tablet', 'Prochlorperazine|5 mg|Tablet', 'Dimenhydrinate|50 mg|Tablet', 'Lozenges (Amylmetacresol + Dichlorobenzyl Alcohol)||Lozenge',
    ],
    'Ophthalmologist' => [
        'Moxifloxacin Eye Drops|0.5%|Drops', 'Ciprofloxacin Eye Drops|0.3%|Drops', 'Tobramycin Eye Drops|0.3%|Drops', 'Chloramphenicol Eye Ointment|1%|Ointment', 'Ofloxacin Eye Drops|0.3%|Drops', 'Prednisolone Eye Drops|1%|Drops', 'Loteprednol Eye Drops|0.5%|Drops', 'Fluorometholone Eye Drops|0.1%|Drops',
        'Ketorolac Eye Drops|0.4%,0.5%|Drops', 'Nepafenac Eye Drops|0.1%|Drops', 'Olopatadine Eye Drops|0.1%,0.2%|Drops', 'Carboxymethylcellulose Eye Drops|0.5%,1%|Drops', 'Hydroxypropyl Methylcellulose Eye Drops|0.3%|Drops', 'Sodium Hyaluronate Eye Drops|0.1%|Drops',
        'Timolol Eye Drops|0.25%,0.5%|Drops', 'Latanoprost Eye Drops|0.005%|Drops', 'Bimatoprost Eye Drops|0.01%,0.03%|Drops', 'Travoprost Eye Drops|0.004%|Drops', 'Brimonidine Eye Drops|0.15%,0.2%|Drops', 'Dorzolamide Eye Drops|2%|Drops', 'Acetazolamide|250 mg|Tablet',
        'Tropicamide Eye Drops|0.5%,1%|Drops', 'Atropine Eye Drops|0.01%,1%|Drops', 'Cyclopentolate Eye Drops|1%|Drops', 'Proparacaine Eye Drops|0.5%|Drops', 'Natamycin Eye Drops|5%|Drops', 'Acyclovir Eye Ointment|3%|Ointment',
    ],
    'Psychiatrist' => [
        'Sertraline|25 mg,50 mg,100 mg|Tablet', 'Escitalopram|5 mg,10 mg,20 mg|Tablet', 'Fluoxetine|10 mg,20 mg,40 mg|Capsule', 'Paroxetine|12.5 mg,25 mg|Tablet', 'Fluvoxamine|50 mg,100 mg|Tablet', 'Venlafaxine|37.5 mg,75 mg,150 mg|Tablet', 'Desvenlafaxine|50 mg,100 mg|Tablet', 'Duloxetine|20 mg,30 mg,60 mg|Capsule',
        'Mirtazapine|7.5 mg,15 mg,30 mg|Tablet', 'Bupropion|150 mg,300 mg|Tablet', 'Amitriptyline|10 mg,25 mg,75 mg|Tablet', 'Imipramine|25 mg,75 mg|Tablet', 'Clomipramine|25 mg,75 mg|Capsule', 'Trazodone|25 mg,50 mg,100 mg|Tablet',
        'Olanzapine|2.5 mg,5 mg,10 mg,20 mg|Tablet', 'Risperidone|1 mg,2 mg,3 mg,4 mg|Tablet', 'Quetiapine|25 mg,50 mg,100 mg,200 mg|Tablet', 'Aripiprazole|5 mg,10 mg,15 mg|Tablet', 'Clozapine|25 mg,100 mg|Tablet', 'Haloperidol|1.5 mg,5 mg|Tablet', 'Amisulpride|50 mg,100 mg,200 mg|Tablet',
        'Lithium Carbonate|300 mg,400 mg|Tablet', 'Sodium Valproate|200 mg,500 mg|Tablet', 'Lamotrigine|25 mg,50 mg,100 mg|Tablet', 'Clonazepam|0.25 mg,0.5 mg,1 mg,2 mg|Tablet', 'Lorazepam|1 mg,2 mg|Tablet', 'Alprazolam|0.25 mg,0.5 mg|Tablet', 'Diazepam|5 mg,10 mg|Tablet', 'Zolpidem|5 mg,10 mg|Tablet', 'Melatonin|3 mg|Tablet', 'Trihexyphenidyl|2 mg|Tablet', 'Propranolol|10 mg,40 mg|Tablet', 'Naltrexone|50 mg|Tablet', 'Disulfiram|250 mg|Tablet', 'Acamprosate|333 mg|Tablet', 'Methylphenidate|5 mg,10 mg|Tablet', 'Atomoxetine|10 mg,18 mg,25 mg|Capsule',
    ],
    'Cardiologist' => [
        'Aspirin|75 mg,150 mg|Tablet', 'Clopidogrel|75 mg|Tablet', 'Ticagrelor|60 mg,90 mg|Tablet', 'Prasugrel|5 mg,10 mg|Tablet', 'Warfarin|1 mg,2 mg,5 mg|Tablet', 'Acenocoumarol|1 mg,2 mg,4 mg|Tablet', 'Apixaban|2.5 mg,5 mg|Tablet', 'Rivaroxaban|10 mg,15 mg,20 mg|Tablet', 'Dabigatran|110 mg,150 mg|Capsule',
        'Rosuvastatin|5 mg,10 mg,20 mg,40 mg|Tablet', 'Ezetimibe|10 mg|Tablet', 'Fenofibrate|145 mg,160 mg|Tablet', 'Metoprolol|25 mg,50 mg,100 mg|Tablet', 'Bisoprolol|2.5 mg,5 mg,10 mg|Tablet', 'Carvedilol|3.125 mg,6.25 mg,12.5 mg,25 mg|Tablet', 'Atenolol|25 mg,50 mg,100 mg|Tablet', 'Nebivolol|2.5 mg,5 mg|Tablet',
        'Ramipril|2.5 mg,5 mg,10 mg|Tablet', 'Enalapril|2.5 mg,5 mg,10 mg|Tablet', 'Lisinopril|5 mg,10 mg|Tablet', 'Losartan|25 mg,50 mg|Tablet', 'Olmesartan|20 mg,40 mg|Tablet', 'Valsartan|40 mg,80 mg,160 mg|Tablet', 'Cilnidipine|5 mg,10 mg|Tablet', 'Sacubitril + Valsartan|24 mg + 26 mg,49 mg + 51 mg,97 mg + 103 mg|Tablet',
        'Hydrochlorothiazide|12.5 mg,25 mg|Tablet', 'Chlorthalidone|6.25 mg,12.5 mg,25 mg|Tablet', 'Indapamide|1.5 mg,2.5 mg|Tablet', 'Furosemide|40 mg|Tablet', 'Torsemide|5 mg,10 mg,20 mg|Tablet', 'Spironolactone|25 mg,50 mg|Tablet', 'Eplerenone|25 mg,50 mg|Tablet', 'Telmisartan + Hydrochlorothiazide|40 mg + 12.5 mg|Tablet', 'Telmisartan + Amlodipine|40 mg + 5 mg|Tablet',
        'Isosorbide Dinitrate|5 mg,10 mg|Tablet', 'Isosorbide Mononitrate|20 mg,30 mg,60 mg|Tablet', 'Glyceryl Trinitrate|0.5 mg|Tablet', 'Nicorandil|5 mg,10 mg|Tablet', 'Ranolazine|500 mg,1000 mg|Tablet', 'Trimetazidine|35 mg|Tablet', 'Ivabradine|5 mg,7.5 mg|Tablet', 'Digoxin|0.25 mg|Tablet', 'Amiodarone|100 mg,200 mg|Tablet', 'Diltiazem|30 mg,60 mg,90 mg|Tablet', 'Verapamil|40 mg,80 mg|Tablet',
    ],
    'Neurologist' => [
        'Levetiracetam|250 mg,500 mg,750 mg,1000 mg|Tablet', 'Phenytoin|100 mg|Tablet', 'Carbamazepine|100 mg,200 mg,400 mg|Tablet', 'Oxcarbazepine|150 mg,300 mg,600 mg|Tablet', 'Lacosamide|50 mg,100 mg|Tablet', 'Topiramate|25 mg,50 mg,100 mg|Tablet', 'Phenobarbitone|30 mg,60 mg|Tablet', 'Clobazam|5 mg,10 mg,20 mg|Tablet',
        'Levodopa + Carbidopa|100 mg + 10 mg,100 mg + 25 mg,250 mg + 25 mg|Tablet', 'Pramipexole|0.25 mg,0.5 mg,1 mg|Tablet', 'Ropinirole|0.25 mg,0.5 mg,1 mg,2 mg|Tablet', 'Rasagiline|0.5 mg,1 mg|Tablet', 'Amantadine|100 mg|Capsule', 'Donepezil|5 mg,10 mg|Tablet', 'Memantine|5 mg,10 mg|Tablet', 'Rivastigmine|1.5 mg,3 mg,6 mg|Capsule',
        'Sumatriptan|25 mg,50 mg,100 mg|Tablet', 'Rizatriptan|5 mg,10 mg|Tablet', 'Flunarizine|5 mg,10 mg|Tablet', 'Gabapentin|100 mg,300 mg,400 mg|Capsule', 'Nortriptyline|10 mg,25 mg|Tablet', 'Pyridostigmine|60 mg|Tablet', 'Citicoline|500 mg|Tablet', 'Piracetam|400 mg,800 mg|Tablet',
    ],
    'Urologist' => [
        'Tamsulosin|0.2 mg,0.4 mg|Capsule', 'Silodosin|4 mg,8 mg|Capsule', 'Alfuzosin|10 mg|Tablet', 'Dutasteride|0.5 mg|Capsule', 'Finasteride|5 mg|Tablet', 'Tamsulosin + Dutasteride|0.4 mg + 0.5 mg|Capsule', 'Solifenacin|5 mg,10 mg|Tablet', 'Tolterodine|1 mg,2 mg|Tablet', 'Mirabegron|25 mg,50 mg|Tablet', 'Oxybutynin|2.5 mg,5 mg|Tablet',
        'Sildenafil|25 mg,50 mg,100 mg|Tablet', 'Tadalafil|5 mg,10 mg,20 mg|Tablet', 'Potassium Citrate|1100 mg/5 ml|Syrup', 'Fosfomycin|3 g|Sachet', 'Flavoxate|200 mg|Tablet', 'Phenazopyridine|100 mg,200 mg|Tablet', 'Cranberry Extract|400 mg|Capsule',
    ],
    'Gastroenterologist' => [
        'Rabeprazole|10 mg,20 mg|Tablet', 'Esomeprazole|20 mg,40 mg|Tablet', 'Lansoprazole|15 mg,30 mg|Capsule', 'Pantoprazole + Domperidone|40 mg + 30 mg|Capsule', 'Famotidine|20 mg,40 mg|Tablet', 'Sucralfate|1 g/5 ml|Syrup', 'Antacid (Aluminium + Magnesium Hydroxide)||Syrup', 'Sodium Alginate + Antacid||Syrup',
        'Itopride|50 mg|Tablet', 'Levosulpiride|25 mg,75 mg|Tablet', 'Metoclopramide|10 mg|Tablet', 'Mebeverine|135 mg,200 mg|Tablet', 'Dicyclomine|10 mg,20 mg|Tablet', 'Drotaverine|40 mg,80 mg|Tablet', 'Hyoscine Butylbromide|10 mg|Tablet', 'Lactulose|10 g/15 ml|Syrup', 'Polyethylene Glycol|17 g|Sachet', 'Bisacodyl|5 mg|Tablet', 'Psyllium Husk||Sachet', 'Sodium Picosulfate|5 mg/5 ml|Syrup',
        'Loperamide|2 mg|Capsule', 'Racecadotril|100 mg|Capsule', 'Rifaximin|200 mg,400 mg,550 mg|Tablet', 'Mesalamine|400 mg,800 mg,1200 mg|Tablet', 'Sulfasalazine|500 mg|Tablet', 'Ursodeoxycholic Acid|150 mg,300 mg|Tablet', 'Silymarin|70 mg,140 mg|Tablet', 'L-Ornithine L-Aspartate|150 mg,500 mg|Tablet', 'Pancreatin|150 mg|Capsule', 'Probiotic Capsule||Capsule', 'Tenofovir|300 mg|Tablet', 'Entecavir|0.5 mg,1 mg|Tablet',
    ],
    'Oncologist' => [
        'Tamoxifen|10 mg,20 mg|Tablet', 'Anastrozole|1 mg|Tablet', 'Exemestane|25 mg|Tablet', 'Bicalutamide|50 mg|Tablet', 'Abiraterone|250 mg,500 mg|Tablet', 'Capecitabine|500 mg|Tablet', 'Temozolomide|100 mg,250 mg|Capsule', 'Imatinib|100 mg,400 mg|Tablet', 'Erlotinib|100 mg,150 mg|Tablet', 'Gefitinib|250 mg|Tablet', 'Sorafenib|200 mg|Tablet', 'Sunitinib|12.5 mg,25 mg,50 mg|Capsule', 'Lenalidomide|5 mg,10 mg,25 mg|Capsule', 'Hydroxyurea|500 mg|Capsule', 'Cyclophosphamide|50 mg|Tablet',
        'Cyclophosphamide|500 mg,1 g|Injection', 'Doxorubicin|10 mg,50 mg|Injection', 'Cisplatin|10 mg,50 mg|Injection', 'Carboplatin|150 mg,450 mg|Injection', 'Oxaliplatin|50 mg,100 mg|Injection', 'Paclitaxel|100 mg,260 mg|Injection', 'Docetaxel|20 mg,80 mg|Injection', 'Gemcitabine|200 mg,1 g|Injection', '5-Fluorouracil|250 mg,500 mg|Injection', 'Trastuzumab|150 mg,440 mg|Injection', 'Rituximab|100 mg,500 mg|Injection', 'Bortezomib|2 mg|Injection', 'Filgrastim|300 mcg|Injection', 'Aprepitant|80 mg,125 mg|Capsule', 'Morphine|10 mg,30 mg|Tablet', 'Fentanyl Patch|25 mcg/hr|Patch', 'Granisetron|1 mg,2 mg|Tablet',
    ],
    'Endocrinologist' => [
        'Carbimazole|5 mg,10 mg,20 mg|Tablet', 'Methimazole|5 mg,10 mg|Tablet', 'Propylthiouracil|50 mg|Tablet', 'Hydrocortisone|5 mg,10 mg|Tablet', 'Fludrocortisone|0.1 mg|Tablet', 'Testosterone|250 mg/ml|Injection', 'Desmopressin|0.1 mg|Tablet', 'Cinacalcet|30 mg|Tablet', 'Levothyroxine|12.5 mcg,88 mcg,112 mcg,125 mcg,150 mcg|Tablet', 'Denosumab|60 mg|Injection',
    ],
    'Diabetologist' => [
        'Gliclazide|40 mg,60 mg,80 mg|Tablet', 'Glipizide|5 mg|Tablet', 'Pioglitazone|7.5 mg,15 mg,30 mg|Tablet', 'Sitagliptin|25 mg,50 mg,100 mg|Tablet', 'Vildagliptin|50 mg|Tablet', 'Linagliptin|5 mg|Tablet', 'Teneligliptin|20 mg|Tablet', 'Dapagliflozin|5 mg,10 mg|Tablet', 'Empagliflozin|10 mg,25 mg|Tablet', 'Canagliflozin|100 mg|Tablet', 'Acarbose|25 mg,50 mg|Tablet', 'Voglibose|0.2 mg,0.3 mg|Tablet', 'Repaglinide|0.5 mg,1 mg|Tablet', 'Metformin + Glimepiride|500 mg + 1 mg,500 mg + 2 mg|Tablet', 'Metformin + Sitagliptin|500 mg + 50 mg,1000 mg + 50 mg|Tablet', 'Metformin + Vildagliptin|500 mg + 50 mg,1000 mg + 50 mg|Tablet',
        'Semaglutide|3 mg,7 mg,14 mg|Tablet', 'Liraglutide|6 mg/ml|Injection', 'Dulaglutide|0.75 mg,1.5 mg|Injection', 'Human Insulin Regular|40 IU/ml,100 IU/ml|Injection', 'Human Insulin NPH|40 IU/ml,100 IU/ml|Injection', 'Human Insulin Premix 30/70|40 IU/ml,100 IU/ml|Injection', 'Insulin Glargine|100 IU/ml|Injection', 'Insulin Aspart|100 IU/ml|Injection', 'Insulin Lispro|100 IU/ml|Injection', 'Insulin Degludec|100 IU/ml|Injection', 'Glucagon|1 mg|Injection',
    ],
    'Pulmonologist' => [
        'Salbutamol|100 mcg|Inhaler', 'Salbutamol Respules|2.5 mg/2.5 ml|Respules', 'Levosalbutamol Inhaler|50 mcg|Inhaler', 'Ipratropium Inhaler|20 mcg|Inhaler', 'Tiotropium Inhaler|9 mcg|Inhaler', 'Budesonide Inhaler|100 mcg,200 mcg|Inhaler', 'Budesonide Respules|0.5 mg,1 mg|Respules', 'Fluticasone + Salmeterol Inhaler|125 mcg + 25 mcg,250 mcg + 25 mcg|Inhaler', 'Budesonide + Formoterol Inhaler|200 mcg + 6 mcg,400 mcg + 6 mcg|Inhaler', 'Levosalbutamol + Ipratropium Respules|1.25 mg + 500 mcg|Respules',
        'Montelukast|4 mg,5 mg,10 mg|Tablet', 'Montelukast + Levocetirizine|10 mg + 5 mg|Tablet', 'Doxofylline|400 mg|Tablet', 'Theophylline|200 mg,400 mg|Tablet', 'Acebrophylline|100 mg|Capsule', 'N-Acetylcysteine|600 mg|Tablet', 'Rifampicin|150 mg,450 mg,600 mg|Capsule', 'Isoniazid|100 mg,300 mg|Tablet', 'Pyrazinamide|500 mg,750 mg|Tablet', 'Ethambutol|400 mg,800 mg|Tablet', 'Pirfenidone|200 mg|Tablet',
    ],
    'Nephrologist' => [
        'Tacrolimus|0.5 mg,1 mg|Capsule', 'Mycophenolate Mofetil|250 mg,500 mg|Tablet', 'Azathioprine|50 mg|Tablet', 'Cyclosporine|25 mg,50 mg,100 mg|Capsule', 'Sevelamer|400 mg,800 mg|Tablet', 'Calcium Acetate|667 mg|Tablet', 'Sodium Bicarbonate|500 mg|Tablet', 'Erythropoietin|2000 IU,4000 IU,10000 IU|Injection', 'Darbepoetin|25 mcg,40 mcg|Injection', 'Iron Sucrose|100 mg/5 ml|Injection', 'Ketoanalogues||Tablet', 'Febuxostat|40 mg,80 mg|Tablet', 'Polystyrene Sulfonate|15 g|Sachet',
    ],
    'Rheumatologist' => [
        'Hydroxychloroquine|200 mg,300 mg,400 mg|Tablet', 'Leflunomide|10 mg,20 mg|Tablet', 'Tofacitinib|5 mg,10 mg|Tablet', 'Colchicine|0.5 mg|Tablet', 'Allopurinol|100 mg,300 mg|Tablet', 'Deflazacort|6 mg,12 mg,24 mg,30 mg|Tablet', 'Folic Acid|1 mg|Tablet', 'Adalimumab|40 mg|Injection', 'Etanercept|25 mg,50 mg|Injection',
    ],
    'Hematologist' => [
        'Deferasirox|250 mg,500 mg|Tablet', 'Iron Carboxymaltose|500 mg,1000 mg|Injection', 'Eltrombopag|25 mg,50 mg|Tablet', 'Vitamin K (Phytomenadione)|10 mg|Injection', 'Vitamin B12 (Cyanocobalamin)|1000 mcg|Injection', 'Heparin|5000 IU/ml|Injection',
    ],
    'General Surgeon' => [
        'Povidone Iodine Ointment|5%|Ointment', 'Silver Sulfadiazine Cream|1%|Cream', 'Lidocaine Gel|2%|Gel', 'Lidocaine + Nifedipine Ointment|1.5% + 0.3%|Ointment', 'Calcium Dobesilate|500 mg|Capsule', 'Diosmin + Hesperidin|450 mg + 50 mg|Tablet', 'Piperacillin + Tazobactam|4.5 g|Injection', 'Serratiopeptidase|10 mg|Tablet',
    ],
    'Neurosurgeon' => [
        'Mannitol|20%|Infusion', 'Nimodipine|30 mg|Tablet',
    ],
];
