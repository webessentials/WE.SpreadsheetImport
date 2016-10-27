<?php
namespace TYPO3\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Add arguments property for serialized values
 */
class Version20161026173055 extends AbstractMigration
{

    /**
     * @return string
     */
    public function getDescription() {
        return '';
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('ALTER TABLE we_spreadsheetimport_domain_model_spreadsheetimport ADD arguments LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE we_spreadsheetimport_domain_model_spreadsheetimport ADD CONSTRAINT FK_19518FA38C9F3610 FOREIGN KEY (file) REFERENCES typo3_flow_resource_resource (persistence_object_identifier)');
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('ALTER TABLE we_spreadsheetimport_domain_model_spreadsheetimport DROP FOREIGN KEY FK_19518FA38C9F3610');
        $this->addSql('ALTER TABLE we_spreadsheetimport_domain_model_spreadsheetimport DROP arguments');
    }
}
