<?php
/*
 * Configuration du formulaire d'inscription.
 * INS_PRIVE = ce dossier « prive » (défini par emplacement.php).
 */
return [
    // Mot de passe de la page admin.php, sous forme chiffrée (hachée).
    // Laissez vide, ouvrez admin.php : la page vous donne la ligne à coller ici.
    'admin_mot_de_passe'  => '',

    // Où se trouvent les descripteurs de formulaires (*.ini) et les fichiers de données (*.csv)
    'dossier_formulaires' => INS_PRIVE . '/formulaires',
    'dossier_donnees'     => INS_PRIVE . '/donnees',

    // Séparateur des fichiers CSV : ';' s'ouvre directement dans Excel en français
    'separateur'          => ';',

    // Fuseau horaire des dates d'inscription
    'fuseau'              => 'Europe/Paris',

    // true : index.php sans paramètre affiche la liste des formulaires
    // false : seuls ceux qui connaissent l'adresse index.php?f=<nom> y accèdent
    'liste_publique'      => true,

    // Longueur maximale d'une réponse (caractères)
    'longueur_max'        => 2000,

    // Adresse d'expédition des courriels de notification (« notifier = » dans un formulaire).
    // Chez beaucoup d'hébergeurs, elle doit appartenir à votre domaine.
    'expediteur'          => '',

    // true : affiche le détail des erreurs (descripteur mal écrit…). À remettre à false ensuite.
    'debug'               => false,
];
