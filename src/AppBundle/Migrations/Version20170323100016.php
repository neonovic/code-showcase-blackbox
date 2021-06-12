<?php

namespace AppBundle\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170323100016 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('CREATE SEQUENCE product_type_parameter_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE product_type_parameter (id INT NOT NULL, product_type_id INT NOT NULL, parameter_group_id INT DEFAULT NULL, parameter_id INT DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, required BOOLEAN DEFAULT \'false\' NOT NULL, filter BOOLEAN DEFAULT \'false\' NOT NULL, collapsed BOOLEAN DEFAULT \'false\' NOT NULL, display_negative_value BOOLEAN DEFAULT \'false\' NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX producttypeparameter_product_type_id_idx ON product_type_parameter (product_type_id)');
        $this->addSql('CREATE INDEX producttypeparameter_parameter_group_id_idx ON product_type_parameter (parameter_group_id)');
        $this->addSql('CREATE INDEX producttypeparameter_parameter_id_idx ON product_type_parameter (parameter_id)');
        $this->addSql('CREATE UNIQUE INDEX ptparameter_ptid_parametergroupid_parameterid ON product_type_parameter (product_type_id, parameter_group_id, parameter_id)');
        $this->addSql('ALTER TABLE product_type_parameter ADD CONSTRAINT FK_CD38AE2C14959723 FOREIGN KEY (product_type_id) REFERENCES product_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_type_parameter ADD CONSTRAINT FK_CD38AE2C132604DB FOREIGN KEY (parameter_group_id) REFERENCES parameter_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_type_parameter ADD CONSTRAINT FK_CD38AE2C7C56DBD6 FOREIGN KEY (parameter_id) REFERENCES parameter (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->addSql('DROP SEQUENCE product_type_parameter_id_seq CASCADE');
        $this->addSql('DROP TABLE product_type_parameter');
    }
}
