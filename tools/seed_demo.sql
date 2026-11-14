-- =============================================================================
-- Donnees de demonstration Patrimoine Etat (a executer sur les bases locales)
-- UUIDs fixes pour les referentiels et les biens.
-- =============================================================================

-- ---------- organisation_db : structures ----------
\c organisation_db
INSERT INTO structures (id, code, libelle, type, statut, adresse, created_at, updated_at) VALUES
('a0000000-0000-4000-8000-000000000001','MIN-ECOF','Ministere de l''Economie et des Finances','ministere','actif','Lome',now(),now()),
('a0000000-0000-4000-8000-000000000002','MIN-EDUC','Ministere de l''Education et de la Formation','ministere','actif','Lome',now(),now()),
('a0000000-0000-4000-8000-000000000003','DSI-ECOF','Direction des Systemes d''Information','direction','actif','Lome',now(),now()),
('a0000000-0000-4000-8000-000000000004','SG-MEF','Secretariat General du MEF','service','actif','Lome',now(),now())
ON CONFLICT (code) DO NOTHING;

-- ---------- organisation_db : categories ----------
INSERT INTO categories (id, code, libelle, duree_vie_mois, methode_amortissement, actif, created_at, updated_at) VALUES
('b0000000-0000-4000-8000-000000000001','CAT-IMM','Immobilier',240,'lineaire',true,now(),now()),
('b0000000-0000-4000-8000-000000000002','CAT-VHC','Vehicule',96,'lineaire',true,now(),now()),
('b0000000-0000-4000-8000-000000000003','CAT-MOB','Mobilier',120,'lineaire',true,now(),now()),
('b0000000-0000-4000-8000-000000000004','CAT-INFO','Informatique',36,'lineaire',true,now(),now()),
('b0000000-0000-4000-8000-000000000005','CAT-AUT','Autre',NULL,'lineaire',true,now(),now())
ON CONFLICT (code) DO NOTHING;

-- ---------- asset_db : biens ----------
\c asset_db
INSERT INTO biens (id, code_inventaire, uuid, designation, description, categorie_id, type_specifique,
                   date_acquisition, mode_acquisition, valeur_acquisition, devise, structure_id,
                   etat, statut, cree_par, created_at, updated_at) VALUES
('c0000000-0000-4000-8000-000000000001','PAT-2024-000001',gen_random_uuid(),'Immeuble administratif Plateau','Batiment de 4 niveaux abritant les services centraux','b0000000-0000-4000-8000-000000000001','immobilier','2021-03-15','construction',185000000,'XOF','a0000000-0000-4000-8000-000000000001','bon','affecte','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000002','PAT-2024-000002',gen_random_uuid(),'Vehicule ministeriel Toyota Land Cruiser','4x4 immatricule AK-4521-TG','b0000000-0000-4000-8000-000000000002','vehicule','2023-06-20','achat',42500000,'XOF','a0000000-0000-4000-8000-000000000001','bon','affecte','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000003','PAT-2025-000003',gen_random_uuid(),'Ordinateur portable Dell Latitude 5540','i7, 16 Go RAM, SSD 512 Go','b0000000-0000-4000-8000-000000000004','informatique','2025-01-10','achat',1850000,'XOF','a0000000-0000-4000-8000-000000000003','bon','affecte','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000004','PAT-2025-000004',gen_random_uuid(),'Serveur Dell PowerEdge R750','2x Xeon Silver, 128 Go RAM','b0000000-0000-4000-8000-000000000004','informatique','2024-09-05','achat',12500000,'XOF','a0000000-0000-4000-8000-000000000003','bon','en_maintenance','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000005','PAT-2025-000005',gen_random_uuid(),'Bureau directionnel en teck massif','Bureau + chaise + credence','b0000000-0000-4000-8000-000000000003','mobilier','2022-11-30','achat',950000,'XOF','a0000000-0000-4000-8000-000000000004','bon','disponible','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000006','PAT-2025-000006',gen_random_uuid(),'Imprimante multifonction HP LaserJet','A3 couleur, recto-verso','b0000000-0000-4000-8000-000000000004','informatique','2024-02-14','achat',780000,'XOF','a0000000-0000-4000-8000-000000000003','bon','affecte','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000007','PAT-2025-000007',gen_random_uuid(),'Vehicule utilitaire Toyota Hilux','Simple cabine, climatisation','b0000000-0000-4000-8000-000000000002','vehicule','2024-04-18','achat',28900000,'XOF','a0000000-0000-4000-8000-000000000002','bon','affecte','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('c0000000-0000-4000-8000-000000000008','PAT-2026-000008',gen_random_uuid(),'Tables de conference x10','Plateau stratifie, 12 places','b0000000-0000-4000-8000-000000000003','mobilier','2025-08-22','achat',2400000,'XOF','a0000000-0000-4000-8000-000000000004','bon','disponible','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now())
ON CONFLICT (code_inventaire) DO NOTHING;

-- ---------- movement_db : affectations ----------
\c movement_db
INSERT INTO mouvements (id, reference, type, statut, bien_id, structure_source_id, structure_dest_id,
                        motif, demandeur_id, niveau_validation, date_demande, date_effet, date_validation, created_at, updated_at) VALUES
('d0000000-0000-4000-8000-000000000001','MVT-2025-0001','affectation','validee','c0000000-0000-4000-8000-000000000002',NULL,'a0000000-0000-4000-8000-000000000001','Mise a disposition du cabinet','01a1da52-27fd-70f8-a942-cea49dd7456a',1,'2025-07-02','2025-07-10',now(),now(),now()),
('d0000000-0000-4000-8000-000000000002','MVT-2025-0002','affectation','validee','c0000000-0000-4000-8000-000000000003',NULL,'a0000000-0000-4000-8000-000000000003','Equipement nouvel agent DSI','01a1da52-27fd-70f8-a942-cea49dd7456a',1,'2025-01-15','2025-01-20',now(),now(),now()),
('d0000000-0000-4000-8000-000000000003','MVT-2025-0003','affectation','validee','c0000000-0000-4000-8000-000000000006',NULL,'a0000000-0000-4000-8000-000000000003','Remplacement imprimante salle serveurs','01a1da52-27fd-70f8-a942-cea49dd7456a',1,'2024-12-05','2024-12-10',now(),now(),now()),
('d0000000-0000-4000-8000-000000000004','MVT-2025-0004','affectation','validee','c0000000-0000-4000-8000-000000000007',NULL,'a0000000-0000-4000-8000-000000000002','Patrouille et missions terrain','01a1da52-27fd-70f8-a942-cea49dd7456a',1,'2024-05-02','2024-05-06',now(),now(),now()),
('d0000000-0000-4000-8000-000000000005','MVT-2026-0005','affectation','brouillon','c0000000-0000-4000-8000-000000000008','a0000000-0000-4000-8000-000000000004','a0000000-0000-4000-8000-000000000003','Salle de reunion direction','01a1da52-27fd-70f8-a942-cea49dd7456a',1,'2026-01-20',NULL,NULL,now(),now())
ON CONFLICT (reference) DO NOTHING;

-- ---------- maintenance_db : interventions ----------
\c maintenance_db
INSERT INTO interventions (id, bien_id, type, statut, description, date_prevue, date_realisee,
                           cout_main_oeuvre, cout_pieces, created_at, updated_at) VALUES
('e0000000-0000-4000-8000-000000000001','c0000000-0000-4000-8000-000000000004','curative','en_cours','Remplacement carte mere et RAM','2026-01-18','2026-01-25',450000,1250000,now(),now()),
('e0000000-0000-4000-8000-000000000002','c0000000-0000-4000-8000-000000000002','preventive','planifiee','Revision generale 100 000 km','2026-02-10',NULL,180000,0,now(),now()),
('e0000000-0000-4000-8000-000000000003','c0000000-0000-4000-8000-000000000007','preventive','planifiee','Controle technique et vidange','2026-02-15',NULL,95000,45000,now(),now()),
('e0000000-0000-4000-8000-000000000004','c0000000-0000-4000-8000-000000000001','preventive','terminee','Desinsectisation et etancheite toiture','2025-11-05','2025-11-12',3200000,850000,now(),now()),
('e0000000-0000-4000-8000-000000000005','c0000000-0000-4000-8000-000000000006','panne','declaree','Bourrage papier recurrent','2026-01-28',NULL,NULL,0,now(),now())
ON CONFLICT (id) DO NOTHING;

-- ---------- inventory_db : campagnes ----------
\c inventory_db
INSERT INTO campagnes (id, code, libelle, structure_id, site_id, categorie_id, date_debut, date_fin,
                       statut, cree_par, created_at, updated_at) VALUES
('f0000000-0000-4000-8000-000000000001','INV-2026-A1','Inventaire annuel national 2026',NULL,NULL,NULL,'2026-01-05','2026-03-31','en_cours','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('f0000000-0000-4000-8000-000000000002','INV-2025-C1','Inventaire informatique DSI',NULL,NULL,'b0000000-0000-4000-8000-000000000004','2025-10-01','2025-10-31','cloturee','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now()),
('f0000000-0000-4000-8000-000000000003','INV-2026-P1','Inventaire des mobilisers MEF',NULL,NULL,'b0000000-0000-4000-8000-000000000003','2026-04-01','2026-04-30','planifiee','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now())
ON CONFLICT (code) DO NOTHING;
