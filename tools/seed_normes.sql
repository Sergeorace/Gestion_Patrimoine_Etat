-- =============================================================================
-- Normes comptables publiques : comptes OHADA sur les categories,
-- champs normes sur les biens, motifs de sortie (directive UEMOA 2012-03).
-- =============================================================================

-- ---------- organisation_db : comptes OHADA par categorie ----------
\c organisation_db
UPDATE categories SET compte_comptable='231', nature='corporelle' WHERE code='CAT-IMM';  -- Constructions
UPDATE categories SET compte_comptable='245', nature='corporelle' WHERE code='CAT-VHC';  -- Materiel de transport
UPDATE categories SET compte_comptable='244', nature='corporelle' WHERE code='CAT-MOB';  -- Materiel et outillage
UPDATE categories SET compte_comptable='246', nature='corporelle' WHERE code='CAT-INFO'; -- Materiel informatique
UPDATE categories SET compte_comptable='248', nature='corporelle' WHERE code='CAT-AUT';  -- Autres immobilisations corporelles

-- ---------- movement_db : motifs de sortie definitifs (UEMOA) ----------
\c movement_db
INSERT INTO motifs_sortie (id, code, libelle, actif, created_at, updated_at) VALUES
(gen_random_uuid(),'CONSOMMATION','Consommation',true,now(),now()),
(gen_random_uuid(),'TRANSFERT','Transfert definitif',true,now(),now()),
(gen_random_uuid(),'REFORME','Reforme',true,now(),now()),
(gen_random_uuid(),'DESTRUCTION','Destruction accidentelle constatee',true,now(),now()),
(gen_random_uuid(),'PERTE','Perte',true,now(),now()),
(gen_random_uuid(),'VOL','Vol',true,now(),now()),
(gen_random_uuid(),'MANQUANT','Manquant constate a l''inventaire',true,now(),now())
ON CONFLICT (code) DO NOTHING;

-- ---------- asset_db : champs normes sur les biens ----------
\c asset_db
UPDATE biens SET compte_comptable='231', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Arrete N°2021-034/PM' WHERE code_inventaire='PAT-2024-000001';
UPDATE biens SET compte_comptable='245', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Marche N°2023-118' WHERE code_inventaire='PAT-2024-000002';
UPDATE biens SET compte_comptable='246', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Marche N°2024-042' WHERE code_inventaire='PAT-2025-000003';
UPDATE biens SET compte_comptable='246', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Marche N°2024-089' WHERE code_inventaire IN ('PAT-2025-000004','PAT-2025-000006');
UPDATE biens SET compte_comptable='244', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Bon de commande N°2022-221' WHERE code_inventaire IN ('PAT-2025-000005','PAT-2026-000008');
UPDATE biens SET compte_comptable='245', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Marche N°2024-156' WHERE code_inventaire='PAT-2025-000007';
UPDATE biens SET compte_comptable='244', nature='corporelle', date_mise_en_service=date_acquisition, reference_decision='Marche N°2022-198' WHERE code_inventaire='PAT-2024-000009';
