<?php
/*
 * Configuration du formulaire d'inscription.
 * INS_PRIVE = ce dossier « prive » (défini par emplacement.php).
 */
return [
    // Langue de l'interface : 'fr' ou 'en' (un fichier par langue dans prive/lang/).
    // Un formulaire peut choisir sa propre langue avec « langue = en » dans son descripteur ;
    // la page admin.php propose aussi le choix de la langue.
    'langue'              => 'fr',

    // true : la page admin.php est active (consultation et téléchargement des inscriptions)
    // false : admin.php est désactivée ; les données se récupèrent uniquement par FTP (dossier donnees/)
    'admin_actif'         => true,

    // true : admin.php permet d'ajouter, remplacer et supprimer des formulaires,
    //        et de supprimer des fichiers de réponses
    // false : admin.php sert seulement à consulter et télécharger (gestion des fichiers par FTP)
    'admin_gestion'       => true,

    // Mot de passe de la page admin.php, sous forme chiffrée (hachée).
    // Laissez vide, ouvrez admin.php : la page vous donne la ligne à coller ici.
    'admin_mot_de_passe'  => '$2y$12$xD3Zi45uk2uL8PvNwpEPjeRUJwDWvdM002wZpvXnfpRRdGF8iOyJq',

    // Où se trouvent les descripteurs de formulaires (*.ini) et les fichiers de données (*.csv)
    'dossier_formulaires' => INS_PRIVE . '/formulaires',
    'dossier_donnees'     => INS_PRIVE . '/donnees',

    // Séparateur des fichiers CSV : ';' s'ouvre directement dans Excel en français
    'separateur'          => ';',

    // Fuseau horaire des dates d'inscription
    'fuseau'              => 'Europe/Paris',

    // true : index.php sans paramètre affiche la liste des formulaires diffusés (« diffusion = oui »)
    // false : pas de liste du tout ; chaque formulaire s'ouvre par son adresse
    'liste_publique'      => true,

    // Longueur maximale d'une réponse (caractères)
    'longueur_max'        => 2000,

    // Adresse d'expédition des courriels de notification (« notifier = » dans un formulaire).
    // Chez beaucoup d'hébergeurs, elle doit appartenir à votre domaine.
    'expediteur'          => '',

    // Symbole monétaire des formulaires de commande (champ « commande »)
    'devise'              => '€',

    // true : affiche le détail des erreurs (descripteur mal écrit…). À remettre à false ensuite.
    'debug'               => false,
];
