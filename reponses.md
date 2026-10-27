
Fichier: reponses.md
reponses aux questions du TD2 Docker
les reponses sont trouvées en  basant sur  les documents fournis et le recherche sur internet et les connaissances personnelles de l'auteur aussi avec l'aide des LLM 

________________________________________
## Question 8.1 
Expliquez pourquoi il est préférable de séparer le Dockerfile, les fichiers applicatifs, les données de la base et les configurations d'exécution.
Quels éléments seront incorporés à l'image et lesquels devront rester extérieurs à celle-ci ?

Il est préférable de séparer le Dockerfile, les fichiers applicatifs, les données de la base et les configurations d'exécution pour plusieurs raisons :
- le code et le Dockerfile (app/) évoluent avec les versions de l’application et sont intégrés à l’image 
- les scripts d’initialisation (db/init/) ne servent qu’au premier démarrage de MariaDB et sont montés, pas copiés dans l’image web 
- les données de la base vivent dans un volume et doivent survivre à la reconstruction des conteneurs 
- les paramètres d’exécution (compose.yaml) décrivent le déploiement et peuvent varier selon l’environnement (dev, prod) sans reconstruire l’image 
- les secrets (secrets/) ne doivent jamais être versionnés ni intégrés à une image.
Séparer limite le contexte de construction au seul dossier app/, ce qui évite d’y envoyer les secrets ou les données, et permet de réutiliser la même image dans plusieurs environnements.

éléments seront incorporés à l'image  : 
- l’image de base PHP/Apache
- les extensions PHP (pdo_mysql) 
- le code applicatif (public/).
À l’extérieur de l'image  : 
- les mots de passe (secrets montés dans /run/secrets/)
- les données MariaDB (volume db_data)
- le script SQL (monté dans le conteneur db) 
- la configuration d’exécution (variables d’environnement, ports, réseaux dans compose.yaml).
________________________________________
## Question 8.3 
- FROM php:8.4-apache : définit l’image de base (PHP 8.4 avec Apache, image officielle). Ses couches constituent la base sur laquelle les instructions suivantes ajoutent les leurs.
- WORKDIR /var/www/html : définit le répertoire de travail pour les instructions suivantes du Dockerfile et pour le conteneur au démarrage. Le dossier est créé s’il n’existe pas.
- COPY public/ /var/www/html/ : copie, au moment du build, le contenu de public/ (pris dans le contexte de construction) dans l’image, à l’emplacement servi par Apache. Cette instruction crée une nouvelle couche.

- ./app : c’est le contexte de construction, le répertoire envoyé au moteur Docker. Docker y cherche le Dockerfile par défaut, et les sources de COPY sont relatives à ce dossier. Les fichiers situés en dehors (comme secrets/) ne sont pas accessibles au build.

- Image ≠ conteneur : une image est un modèle immuable en lecture seule (couches de fichiers et configuration). Un conteneur est une instance en cours d’exécution de cette image, avec ses processus, son réseau et sa couche inscriptible. Plusieurs conteneurs peuvent être créés depuis la même image. Les modifications faites dans un conteneur ne changent pas l’image et disparaissent à sa suppression.
________________________________________
## Question 8.4 

Docker consulte le fichier .dockerignore au moment de la construction de l'image, avant d'envoyer le contexte de construction au moteur Docker. Il utilise ce fichier pour déterminer quels fichiers et répertoires doivent être exclus du contexte de construction.

Si le contexte de construction contenait une archive de plusieurs gigaoctets, des fichiers temporaires ou des identifiants sensibles, cela pourrait entraîner plusieurs problèmes :
- L'envoi d'une archive volumineuse ralentirait considérablement le processus de construction et pourrait même provoquer des échecs si le système de fichiers ou le réseau ne peut pas gérer la taille.
- Les fichiers temporaires pourraient encombrer l'image finale, augmentant sa taille inutilement et rendant le déploiement plus lent.
- Les identifiants sensibles inclus dans le contexte de construction pourraient être accidentellement intégrés à l'image, exposant ainsi des informations confidentielles à toute personne ayant accès à l'image, ce qui constitue un risque de sécurité majeur.

.dockerignore est donc un outil utile pour exclure certains fichiers du contexte de construction, mais il ne remplace pas une politique de protection des secrets. Une politique de protection des secrets implique des pratiques de sécurité plus larges, telles que le stockage sécurisé des secrets, l'utilisation de gestionnaires de secrets, la rotation régulière des clés et l'accès restreint aux informations sensibles.
________________________________________
## Question 8.5 

1- Que se passe-t-il si vous redémarrez MariaDB avec un volume contenant déjà une base de données ?
2- Le script SQL sera-t-il exécuté une seconde fois ?
3- Comment appliqueriez-vous une évolution de schéma à une base déjà en production ?
réponses :
1- Si vous redémarrez MariaDB avec un volume contenant déjà une base de données, le serveur MariaDB détectera que la base de données existe déjà et ne réinitialisera pas les données. Il utilisera les données existantes dans le volume pour continuer à fonctionner normalement.
2- Non, le script SQL ne sera pas exécuté une seconde fois. Les scripts d'initialisation sont conçus pour être exécutés uniquement lors de la première création de la base de données. Si la base de données existe déjà, MariaDB ignore ces scripts pour éviter de réinitialiser ou de corrompre les données existantes.
3-j'ai jamais fait ca personnelement mais selon mes recherche Le script 01-init.sql ne sert qu’au tout premier démarrage. Sur une base qui existe déjà, il n’est plus jamais exécuté, donc le modifier ne change rien.
Pour faire évoluer la base en production, il faut :
- Écrire un nouveau script qui contient seulement la modification (par exemple ajouter une colonne), et le numéroter (002-...sql) pour garder l’historique des changements.
- Faire une sauvegarde de la base avant de toucher à quoi que ce soit(toujours on sait jamais).
- Tester le script sur une copie de la base, pas directement en production.
- L’appliquer sur la vraie base, par exemple avec docker compose exec, ou avec un outil de migration (Flyway, Liquibase) qui retient les scripts déjà passés.
- Vérifier que l’application fonctionne toujours après la modification .
________________________________________
## Question 8.6 — Gestion des secrets

1- Pourquoi inscrire un mot de passe avec l'instruction ENV d'un Dockerfile présente-t-il un risque ?
2- Quelle différence existe-t-il entre une variable d'environnement, un fichier .env et un secret monté dans /run/secrets/ ?
1- Inscrire un mot de passe avec l'instruction ENV d'un Dockerfile présente un risque car cette information est stockée dans l'image Docker. Toute personne ayant accès à l'image peut extraire le mot de passe en inspectant l'image ou en utilisant des commandes comme `docker history` ou `docker inspect`. Cela expose les informations sensibles à des utilisateurs non autorisés et constitue une faille de sécurité.
2- La différence entre une variable d'environnement, un fichier .env et un secret monté dans /run/secrets/ réside principalement dans la manière dont ils sont gérés et sécurisés :
- Une variable d'environnement est définie dans le conteneur et peut être visible par tous les processus du conteneur. Elle peut être définie dans le Dockerfile, dans le fichier docker-compose.yml ou via la ligne de commande lors du démarrage du conteneur. Cependant, elle n'est pas sécurisée et peut être exposée si le conteneur est compromis.
- Un fichier .env est un fichier texte qui contient des paires clé-valeur pour les variables d'environnement. Il est généralement utilisé pour stocker des configurations et peut être lu par Docker Compose pour définir les variables d'environnement. Cependant, si ce fichier est versionné ou partagé, il peut également exposer des informations sensibles.
- Un secret monté dans /run/secrets/ est une méthode plus sécurisée pour gérer les informations sensibles. Les secrets sont stockés de manière sécurisée par Docker et ne sont accessibles qu'aux conteneurs qui en ont besoin. Ils ne sont pas stockés dans l'image et ne sont pas exposés dans les variables d'environnement, ce qui réduit le risque d'exposition des informations sensibles.    
Question 8.7 — Diagnostic et reconstruction
Quel message permettait d'identifier l'origine de la panne ?
Pourquoi fallait-il modifier l'image plutôt que simplement redémarrer le conteneur ?
Quelle différence existe-t-il entre docker compose restart et docker compose up --build -d ?


________________________________________
## Question 8.7 — Diagnostic et reconstruction

1-Quel message permettait d'identifier l'origine de la panne ?
2-Pourquoi fallait-il modifier l'image plutôt que simplement redémarrer le conteneur ?
3-Quelle différence existe-t-il entre docker compose restart et docker compose up --build -d ?

1-Le message qui révélait la panne : l’application répondait 503 {"status":"unavailable"}, et les journaux du service web (docker compose logs web) contenaient could not find driver. PHP ne trouvait pas le pilote MySQL. php -m l’a confirmé : seul pdo_sqlite était présent, pas pdo_mysql.
2- Pourquoi modifier l’image et non redémarrer ? Le pilote manquait dans l’image elle-même. Redémarrer le conteneur relance la même image, donc la même panne. Et l’installer à la main dans le conteneur serait perdu à sa suppression, en plus de ne pas être reproductible. Il fallait donc l’ajouter dans le Dockerfile et reconstruire l’image.
3- docker compose restart redémarre les conteneurs existants sans reconstruire l’image. Les modifications dans le Dockerfile ne sont pas prises en compte. docker compose up --build -d reconstruit l’image si nécessaire et recrée les conteneurs avec la nouvelle image, prenant en compte les changements du Dockerfile.
________________________________________
## Question 8.8 — Résolution des services
1- Pourquoi le nom db permet-il à PHP de joindre MariaDB ?
2- Est-il nécessaire de connaître son adresse IP ?
3- Que se passe-t-il si le conteneur MariaDB est recréé avec une nouvelle adresse ?

1- Le nom db permet à PHP de joindre MariaDB grâce au système de résolution de noms intégré à Docker Compose. Chaque service défini dans le fichier compose.yaml est automatiquement ajouté à un réseau interne, et Docker Compose configure un DNS interne qui permet aux conteneurs de se résoudre par leurs noms de service. Ainsi, le conteneur web peut utiliser le nom db pour se connecter au conteneur MariaDB sans avoir besoin de connaître son adresse IP.
2- Il n’est pas nécessaire de connaître l’adresse IP du conteneur MariaDB. Les conteneurs peuvent communiquer entre eux en utilisant les noms de service définis dans le fichier compose.yaml, ce qui rend la configuration plus flexible et moins sujette aux changements d’adresses IP.
3- Si le conteneur MariaDB est recréé avec une nouvelle adresse IP, le conteneur web pourra toujours se connecter à MariaDB en utilisant le nom de service db.

________________________________________
## Question 8.9 — Cloisonnement
1- Pourquoi séparer les réseaux frontend et backend ?
2- Quelle serait la conséquence d'une publication du port 3306 avec ports: "3306:3306" ?
3- L'absence de port publié signifie-t-elle qu'un service est inaccessible depuis tous les autres conteneurs ?

1- Séparer les réseaux frontend et backend permet de cloisonner les communications entre les services. Le réseau frontend est destiné aux services qui doivent être accessibles depuis l’extérieur (comme le service web), tandis que le réseau backend est réservé aux services internes (comme la base de données). Cela renforce la sécurité  et réduisant la surface d’attaque.
2- La publication du port 3306 avec ports: "3306:3306" exposerait le service MariaDB à l’extérieur du réseau Docker. Cela augmenterait le risque de compromission de la sécurité 
3- L'absence de port publié signifie que le service n'est pas accessible depuis l'extérieur du réseau Docker, mais il reste accessible depuis les autres conteneurs du même réseau.
________________________________________
## Question 8.10 — Analyse réseau
1- Distinguez :
•	Le réseau interne des conteneurs.
•	Le port d'écoute du processus Apache.
•	Le port publié sur l'hôte.
•	Le mécanisme de résolution DNS interne.
2- Expliquez pourquoi l'adresse IP d'un conteneur ne doit pas constituer une dépendance permanente de la configuration applicative.
1-
- Réseau interne : chaque conteneur a une adresse IP privée sur le réseau Docker. Seuls les conteneurs du même réseau se voient.
- Port d’Apache : Apache écoute sur le port 80 à l’intérieur du conteneur. L’hôte ne le voit pas directement.
- Port publié : 127.0.0.1:8080:80 redirige ton port 8080 vers le port 80 du conteneur. C’est la porte d’entrée depuis ta machine.
- DNS interne : Docker traduit le nom db en adresse IP. Ça permet à web de trouver la base sans connaître son adresse.
* Pourquoi pas d’IP fixe ? L’adresse IP change quand le conteneur est recréé. Si elle est écrite en dur, l’application ne fonctionne plus. Le nom db reste toujours le même.

________________________________________
## Question 8.11 — Démarrage
1- Quelle différence existe-t-il entre :
•	Un conteneur démarré.
•	Un processus serveur actif.
•	Un service réellement prêt à répondre aux demandes ?
2- Pourquoi un conteneur peut-il apparaître comme running alors que l'application est inutilisable ?


1-
- Conteneur démarré : Docker a lancé le conteneur. Il affiche running, mais ça ne dit pas si l’application marche.
- Processus serveur actif : le programme tourne, mais il peut encore être en train de s’initialiser. Par exemple, MariaDB prépare ses données avant d’accepter les connexions. 
- Service prêt : le service répond vraiment aux demandes. C’est le seul niveau qui compte pour l’utilisateur.

2- Pourquoi running mais inutilisable ? Docker vérifie seulement que le processus est vivant, pas qu’il fonctionne. L’application peut être en cours d’initialisation, ou renvoyer des erreurs.

## Question 8.12 — Disponibilité
1- Quel avantage présente service_healthy par rapport à service_started ?
2- Cette configuration garantit-elle que MariaDB restera disponible pendant toute la durée de vie de l'application ?
3- Quelle autre responsabilité doit être assumée par le code applicatif en cas d'interruption temporaire de la base ?

1- Avantage de service_healthy : il indique que le service est prêt à répondre aux demandes, pas seulement qu’il a démarré. Cela permet de s’assurer que l’application ne tente pas de se connecter à une base qui n’est pas encore prête.
2- Non, cette configuration ne garantit pas que MariaDB restera disponible pendant toute la durée de vie de l'application. Elle ne fait que vérifier l'état au démarrage. Des interruptions peuvent survenir pour diverses raisons (redémarrage du conteneur, panne du serveur, etc.).
3- Responsabilité du code applicatif : le code doit gérer les interruptions temporaires de la base de données, par exemple en implémentant des mécanismes de reconnexion, des files d'attente de requêtes, ou des stratégies de tolérance aux pannes pour assurer la continuité du service
________________________________________

## Question 8.13 — Diagnostic d'incident
1- Pourquoi le serveur web peut-il rester actif alors que l'application ne fonctionne plus ?
2- Quels éléments permettent de distinguer une panne réseau, une indisponibilité de MariaDB et une erreur dans le code PHP ?
3- Quelle amélioration proposeriez-vous pour assurer une meilleure tolérance aux interruptions temporaires ?

1- Le serveur web peut rester actif même si l'application ne fonctionne plus parce que le processus Apache peut être en cours d'exécution, mais l'application PHP qu'il sert peut rencontrer des erreurs ou des problèmes de connexion à la base de données. Ainsi, le serveur web peut répondre aux requêtes HTTP, mais l'application elle-même peut échouer à traiter ces requêtes correctement.
2- Pour distinguer une panne réseau, une indisponibilité de MariaDB et une erreur dans le code PHP, on peut examiner les journaux du serveur web et de la base de données. Une panne réseau peut se manifester par des erreurs de connexion ou des délais d'attente. Une indisponibilité de MariaDB peut être identifiée par des messages d'erreur spécifiques liés à la base de données, tandis qu'une erreur dans le code PHP peut générer des messages d'erreur ou des exceptions dans les journaux du serveur web.
3- Pour assurer une meilleure tolérance aux interruptions temporaires, on pourrait mettre en place des mécanismes de reprise automatique dans l'application, tels que des tentatives de reconnexion avec des délais d'attente progressifs, l'utilisation de files d'attente pour gérer les requêtes en attente, et la mise en place de tests de santé pour surveiller l'état de la base de données et du serveur web. De plus, l'utilisation de solutions de mise en cache ou de réplication de la base de données pourrait également améliorer la résilience de l'application face aux interruptions temporaires.

 
## Question 8.14 — Types de stockage
1-Quelle différence existe-t-il entre :
•	La couche inscriptible d'un conteneur.
•	Un volume Docker nommé.
•	Un bind mount.
2- Pourquoi avons-nous choisi un volume nommé pour /var/lib/mysql ?
3- Dans quel cas un bind mount serait-il préférable?

1- 
- Couche inscriptible : espace temporaire créé dans le conteneur. Les données disparaissent quand le conteneur est supprimé.
- Volume nommé : espace géré par Docker, en dehors du conteneur. Les données restent même si le conteneur est supprimé.
- Bind mount : dossier de ta machine monté dans le conteneur. Tu vois et modifies directement les fichiers.
2- Nous avons choisi un volume nommé pour /var/lib/mysql afin de garantir la persistance des données de la base de données MariaDB. En utilisant un volume nommé, les données restent intactes même si le conteneur MariaDB est supprimé ou recréé, ce qui est essentiel pour maintenir l'intégrité et la continuité des informations stockées dans la base de données.
3- Un bind mount serait préférable dans les cas où l'on souhaite un accès direct aux fichiers depuis l'hôte, par exemple pour le développement ou le débogage. Cela permet de modifier les fichiers de l'application ou de la configuration en temps réel sans avoir à reconstruire l'image ou redémarrer le conteneur. Cependant, il faut être prudent avec les bind mounts en production, car ils peuvent introduire des risques de sécurité et de stabilité si les fichiers de l'hôte sont modifiés de manière inattendue.
