-- =============================================================================
-- Donnees geographiques et referentiels metier pour les rapports
-- (region / prefecture / commune sur les sites, domaine / secteur sur les biens)
-- =============================================================================

-- ---------- organisation_db : sites geo ----------
\c organisation_db
INSERT INTO sites (id, code, libelle, structure_id, region, prefecture, commune, adresse, created_at, updated_at) VALUES
('f0000000-0000-4000-8000-000000000001','SITE-MEF-LOME','Siege MEF - Lomé','a0000000-0000-4000-8000-000000000001','Maritime','Lomé','Agoè-Nyivé','Boulevard du 13 janvier, Lomé',now(),now()),
('f0000000-0000-4000-8000-000000000002','SITE-EDUC-ATA','Lycee du Centre - Atakpamé','a0000000-0000-4000-8000-000000000002','Plateaux','Atakpamé','Atakpamé','Route de Kara, Atakpamé',now(),now()),
('f0000000-0000-4000-8000-000000000003','SITE-DSI-DILI','Datacenter - Dili','a0000000-0000-4000-8000-000000000003','Maritime','Yoto','Danyi','Zone industrielle, Dili',now(),now()),
('f0000000-0000-4000-8000-000000000004','SITE-SG-KARA','Annexe SG - Kara','a0000000-0000-4000-8000-000000000004','Kara','Kozah','Kara','Boulevard de la Kara, Kara',now(),now())
ON CONFLICT (code) DO NOTHING;

-- ---------- asset_db : rattachement geo + domaine/secteur ----------
\c asset_db
UPDATE biens SET site_id='f0000000-0000-4000-8000-000000000001', domaine='domaine_public_immobilier', secteur='Administration centrale' WHERE code_inventaire='PAT-2024-000001';
UPDATE biens SET site_id='f0000000-0000-4000-8000-000000000001', domaine='biens_meubles', secteur='Transport et logistique' WHERE code_inventaire IN ('PAT-2024-000002','PAT-2025-000007');
UPDATE biens SET site_id='f0000000-0000-4000-8000-000000000003', domaine='infrastructures', secteur='Systemes d''information' WHERE code_inventaire IN ('PAT-2025-000003','PAT-2025-000004','PAT-2025-000006');
UPDATE biens SET site_id='f0000000-0000-4000-8000-000000000004', domaine='biens_meubles', secteur='Mobilier de bureau' WHERE code_inventaire IN ('PAT-2025-000005','PAT-2026-000008');

-- Bien supplementaire (education) pour couvrir ce domaine dans les rapports
INSERT INTO biens (id, code_inventaire, uuid, designation, description, categorie_id, type_specifique,
                   date_acquisition, mode_acquisition, valeur_acquisition, devise, structure_id, site_id,
                   domaine, secteur, etat, statut, cree_par, created_at, updated_at) VALUES
('c0000000-0000-4000-8000-000000000009','PAT-2024-000009',gen_random_uuid(),'Tables et chaises scolaires x40','Equipement de 2 salles de classe','b0000000-0000-4000-8000-000000000003','mobilier','2022-09-12','achat',3600000,'XOF','a0000000-0000-4000-8000-000000000002','f0000000-0000-4000-8000-000000000002','education','Equipements pedagogiques','bon','affecte','01a1da52-27fd-70f8-a942-cea49dd7456a',now(),now())
ON CONFLICT (code_inventaire) DO NOTHING;
