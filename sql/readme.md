ALTER TABLE lms_admin.admin ADD COLUMN foto VARCHAR(255) DEFAULT NULL;
ALTER TABLE lms_dosen.dosen ADD COLUMN foto VARCHAR(255) DEFAULT NULL;
ALTER TABLE lms_mahasiswa.mahasiswa ADD COLUMN foto VARCHAR(255) DEFAULT NULL;

ALTER TABLE lms_admin.kelas
ADD COLUMN fakultas_id INT DEFAULT NULL AFTER dosen_id,
ADD COLUMN prodi_id INT DEFAULT NULL AFTER fakultas_id,
ADD COLUMN jml_week INT NOT NULL DEFAULT 14;

ALTER TABLE lms_admin.kelas
ADD FOREIGN KEY (fakultas_id) REFERENCES lms_admin.fakultas(id) ON UPDATE CASCADE ON DELETE SET NULL,
ADD FOREIGN KEY (prodi_id) REFERENCES lms_admin.prodi(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE lms_dosen.materi ADD COLUMN week INT NOT NULL DEFAULT 1 AFTER kelas_id;
ALTER TABLE lms_dosen.tugas ADD COLUMN week INT NOT NULL DEFAULT 1 AFTER kelas_id;

update data base kalian jika kalian udah ada database ini guys. 