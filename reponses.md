
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

## Question 8.8 — Résolution des services
1- Pourquoi le nom db permet-il à PHP de joindre MariaDB ?
2- Est-il nécessaire de connaître son adresse IP ?
3- Que se passe-t-il si le conteneur MariaDB est recréé avec une nouvelle adresse ?

1- Le nom db permet à PHP de joindre MariaDB grâce au système de résolution de noms intégré à Docker Compose. Chaque service défini dans le fichier compose.yaml est automatiquement ajouté à un réseau interne, et Docker Compose configure un DNS interne qui permet aux conteneurs de se résoudre par leurs noms de service. Ainsi, le conteneur web peut utiliser le nom db pour se connecter au conteneur MariaDB sans avoir besoin de connaître son adresse IP.
2- Il n’est pas nécessaire de connaître l’adresse IP du conteneur MariaDB. Les conteneurs peuvent communiquer entre eux en utilisant les noms de service définis dans le fichier compose.yaml, ce qui rend la configuration plus flexible et moins sujette aux changements d’adresses IP.
3- Si le conteneur MariaDB est recréé avec une nouvelle adresse IP, le conteneur web pourra toujours se connecter à MariaDB en utilisant le nom de service db.

## Question 8.9 — Cloisonnement
1- Pourquoi séparer les réseaux frontend et backend ?
2- Quelle serait la conséquence d'une publication du port 3306 avec ports: "3306:3306" ?
3- L'absence de port publié signifie-t-elle qu'un service est inaccessible depuis tous les autres conteneurs ?

1- Séparer les réseaux frontend et backend permet de cloisonner les communications entre les services. Le réseau frontend est destiné aux services qui doivent être accessibles depuis l’extérieur (comme le service web), tandis que le réseau backend est réservé aux services internes (comme la base de données). Cela renforce la sécurité  et réduisant la surface d’attaque.
2- La publication du port 3306 avec ports: "3306:3306" exposerait le service MariaDB à l’extérieur du réseau Docker. Cela augmenterait le risque de compromission de la sécurité 
3- L'absence de port publié signifie que le service n'est pas accessible depuis l'extérieur du réseau Docker, mais il reste accessible depuis les autres conteneurs du même réseau.

## Question 8.10 — Analyse réseau
