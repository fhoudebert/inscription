<?php
/*
 * Emplacement de la zone privée (configuration, descripteurs de formulaires, données).
 *
 * Par défaut : le dossier « prive » placé à côté de ce fichier.
 * Si votre hébergeur le permet, déplacez ce dossier HORS de la partie publique du site
 * (à côté de www/ ou public_html/) et indiquez ici son chemin complet, par exemple :
 *
 *     return '/home/moncompte/inscription-prive';
 *     return dirname(__DIR__, 2) . '/inscription-prive';
 */
return __DIR__ . '/prive';
