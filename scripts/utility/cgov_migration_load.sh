#!/bin/bash
# Min migration scripts
#
set -ev

if [ $MIGRATION = 1 ]; then
    drush mim summary_migration && drush mim summaryes_migration && drush mim dis_migration
    drush mim externallinksql_migration; drush mim internallinksql_migration; drush mim citation_migration
    drush mim paragraph_en_migration; drush mim paragraph_es_migration
    drush mim cgovimage_migration && drush mim cgovimage_es_migration
    drush mim pressrelease_en_migration && drush mim pressrelease_es_migration
    drush mim article_en_migration && drush mim article_es_migration
    drush mim video_en_migration && drush mim video_es_migration
    drush mim infographic_en_migration && drush mim infographic_es_migration

    drush mim region_migration && drush mim cancercentertype_migration && drush mim cancercenter_migration

    drush mim blogseries_en_migration && drush mim blogseries_es_migration && drush mim blogtopics_migration && drush mim blogtopics_es_migration
    drush mim blogpost_en_migration && drush mim blogpost_es_migration


    drush mim cancerresearch_en_migration && drush mim cancerresearch_es_migration

    drush mim contextualimage_migration && drush mim contextualimage_es_migration

else
    echo 'MIGRATION NOT SET'
fi

set +v
