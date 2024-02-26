"use strict";

var baseURL = "https://wsgroups-test.univ-paris1.fr";
var searchUserURL = baseURL + '/searchUser?CAS=MFA';
var getGroupURL = baseURL + '/getGroup';
var lastLoginsUrl = baseURL + '/userLastLogins';
var moreInfoUrl = baseURL + '/userMoreInfo?CAS=MFA';
var helpUrl = 'https://dsiundoc.univ-paris1.fr/doku.php?id=refi:userinfo-web#HELP_ID';
helpUrl = 'https://idp.univ-paris1.fr/idp/profile/Shibboleth/SSO?shire=https://dsiundoc.univ-paris1.fr/Shibboleth.sso/SAML/POST&target=' + escape(helpUrl) + '&providerId=https://dsiundoc.univ-paris1.fr';
var impersonateUrl = 'https://ent-test.univ-paris1.fr/EsupUserApps/impersonate.html';
var appsUrl = 'https://ent-test.univ-paris1.fr/EsupUserApps/layout';
var apogeeStudentDetailUrl = 'https://apogee.univ-paris1.fr/up1/jsp/detail_etudiant.jsp?config=apoprod&cod_etu=';
var userphotoUrl = 'https://userphoto-test.univ-paris1.fr/';
var grouperUrl = 'https://grouper-test.univ-paris1.fr/grouper/grouperUi/app/UiV2Main.index?operation=UiV2Group.viewGroup&groupName=';

function toGrouperUrl(cn) {
    return grouperUrl + cn.replace(/\./g, ":")
}

function parse_attrs_text(l) {
    return $.map(l, function (attr_text) {
	var r = attr_text && attr_text.match(/(.*?): (.*)/);
	if (!r) console.error("invalid attr_text " + attr_text);
	return r && { attr: r[1], text: r[2] };
    });
}

var main_attrs_labels = [ [
    'Person: Personne',

    'eduPersonAffiliation: Statut(s)',
    'businessCategory: Catégorie',
    
    'employeeType: Type',
    'supannCodePopulation: Type',
    "supannRoles: Fonction(s) d'encadrement ou transverses",

    'supannActivite-REFERENS: Emploi type REFERENS <small>(RH)</small>',
    'supannActivite-RIFSEEP: Emploi RIFSEEP <small>(RH)</small>',
    'supannActivite-CNU: Discipline(s) <small>(RH)</small>',
    "supannActivite-various: supannActivite-various", 
    //'departmentNumber: Discipline(s)', // redundant

    'description: Activité<br><small>(choisi dans Mon Compte Paris 1)</small>',
    "info: Domaine d'activité ou Spécialité<br><small>(saisie libre dans Mon Compte Paris 1)</small>",

    'supannEntiteAffectation-all: Structure(s)/Composante(s)',
    'Responsable: Responsable',

    'eduPersonOrgUnitDN: Formation(s)',
    'eduPersonEntitlement: Formation spéciale',

    //les attributs renvoyant des DN
    'seeAlso-all: Rattachement',
    'supannParrainDN-all: Parrainé par',

    // les attributs faisant appel aux tables de nomenclature LDAP
    'supannEtablissement: Etablissement tiers',

    'supannEtuInscription-all: Inscription(s)',
    'supannEtuInscription-prev: Ancienne(s) inscription(s)',
    'supannEtuEtape: Etape',
    'supannEtuAnneeInscription: Année',
    'eduPersonOrgUnitDN: Affectation(s)',
],
[   
    'mail: Courriel',
    'mailAlternateAddress: Courriel secondaire',
    'supannAutreMail: Courriel secondaire',

    'telephoneNumber: Téléphone fixe',
    'supannAutreTelephone: Tél secondaire',
    'mobile: Tél mobile',
    'facsimileTelephoneNumber: Fax',

    'labeledURI: Page personnelle',

    'buildingName: Site',
    'roomNumber: Bureau',
    'up1FloorNumber: Etage',
    'up1RoomAccess: Précision du lieu',
    'postalAddress: Adresse professionnelle',
],
[
    'Identifiers: Identifiant',
    'OtherIdentifiers: Identifiant(s)',
    'supannFCSub: Identifiant délégation France Connect',
    'accountStatus: Etat du compte',
    'Account: Compte',
    'shadowExpire: Expire le',
    'createTimestamp: Compte créé',
    'modifyTimestamp: Dernière modification',
    'supannExtProfil-all: Profil(s) externe',
    'supannEmpProfil-all: Profil(s) employé',
    'up1Source: Profil',
    'up1Profile: Profils',
    'supannRefId: RefIds',
],
[
    'Mailbox: Boîte mail',
    'sambaHomePath: Dossier de travail',
    'Folder: ',
],
[
    'up1Roles: Courriel(s) de fonction',

    //'supannRefID: RefId',
    'supannListeRouge: ' + important('Liste rouge'),
    "up1TermsOfUse: Conditions d'utilisation",
    "supannConsentement: Conditions d'utilisation",
],
[
			  
    'supannMailPerso: Courriel personnel',
    'mailForwardingAddress: Messagerie',

    'pager: Tél mobile',
    'Photo: Photo',
],
[
    'Listes: Listes',
    'supannGroupeAdminDN-all: Responsables',
    'supannGroupeLecteurDN-all: Observateurs',
    'member-all: Membres',
    'Applications: Applications',
],
[
    'memberOf-all: Groupes',
]
];

var sub_attrs_labels = {
    'Identifiers': 
    [ 'supannEmpId: numéro agent'
    , 'supannEtuId: code étudiant'
    , 'supannCodeINE: INE'
    , 'uidNumber: UID NAS'
    , 'employeeNumber: code-barre'
    , 'up1TagMifare: tag Mifare'
  //, 'eduPersonPrincipalName: EPPN'
    ]
};
    

var eduPersonAffiliation_valnames = {
	'student': "étudiant",
	'staff': "BIATSS",
	'teacher': "enseignant",
	'researcher': "chercheur",
	'affiliate': "partenaire extérieur",
	'alum': "ancien étudiant",
	'emeritus': "professeur émérite",
	'retired': "retraité",
	'employee': "employé",
	'registered-reader': "lecteur externe",
	'library-walk-in': "visiteur bibliothèque",
	'internal': "compte interne",
};

function important(s, helpPage, className) {
    var span = "<span class='" + (className || "important") + "'>" + s + "</span>";
    if (helpPage) {
	var url = helpUrl.replace('HELP_ID', helpPage);
	var openOptions = 'toolbar=no,scrollbars=yes,alwaysRaised,width=1000,height=400';
	var onclick = 'window.open("", "form_help", "' + openOptions + '")';
	var help_span = "<a href='" + url + "' onclick='" + onclick + "' target='form_help' class='help'>?</a>";
	span = span + help_span;
    }
    return span;
}

var attr2valnames = {
    'accountStatus': {
	'active': "ACTIF",
	'noaccess': important("VERROUILLÉ"),
	'disabled': important("DÉSACTIVÉ", 'status-expire'),
	'deleted': important("PURGÉ"),
    },
    'shadowFlag': {
	2: "DOUBLON",
	8: "DÉCÉDÉ",
//	9: "VERROUILLÉ",
    },
    //'businessCategory': {
    //	'administration': "administration",
    //	'pedagogy': "pédagogie",
    //	'library': "bibliothèque",
    //	'trade union': "syndicat",
    //	'research': "recherche",
    //	'council': "conseils et commissions",
    //	'web': "web",
    //	'test': "test",
    //},
    'objectClass': {
	'up1Person': 'Personne',
	'up1Role': 'Compte de fonction',
    }    
};

var simple_formatters = {
    supannListeRouge: function (val) { return val === "TRUE" && important("oui") },
    shadowExpire: format_shadowExpire,   
    createTimestamp: format_timestamp,
    modifyTimestamp: format_timestamp,
    up1Roles: format_up1Roles,
    labeledURI: format_link,
    'supannGroupeAdminDN-all': format_members,
    'supannGroupeLecteurDN-all': format_members_not_admin,
    'member-all': format_members,
    'memberOf-all': format_memberOf,
    'seeAlso-all': format_supannCodeEntite,
    'supannParrainDN-all': format_supannCodeEntite,
    'supannEntiteAffectation-all': format_supannEntiteAffectation,
    'supannEmpProfil-all': format_supannEmpExtProfilAll,
    'supannExtProfil-all': format_supannEmpExtProfilAll,
    supannRefId: format_supannRefId,
    mailForwardingAddress: compute_MailDelivery,
    up1Profile: format_main_profiles_info,
    up1Source: function (_, info) { return format_main_profile_info(info) },
};

var diacriticsMap = [
      {'base':'A', 'letters':/[\u0041\u24B6\uFF21\u00C0\u00C1\u00C2\u1EA6\u1EA4\u1EAA\u1EA8\u00C3\u0100\u0102\u1EB0\u1EAE\u1EB4\u1EB2\u0226\u01E0\u01DE\u1EA2\u00C5\u01FA\u01CD\u0200\u0202\u1EA0\u1EAC\u1EB6\u1E00\u0104\u023A\u2C6F]/g},
      {'base':'AA','letters':/[\uA732]/g},
      {'base':'AE','letters':/[\u00C4\u00C6\u01FC\u01E2]/g},
      {'base':'AO','letters':/[\uA734]/g},
      {'base':'AU','letters':/[\uA736]/g},
      {'base':'AV','letters':/[\uA738\uA73A]/g},
      {'base':'AY','letters':/[\uA73C]/g},
      {'base':'B', 'letters':/[\u0042\u24B7\uFF22\u1E02\u1E04\u1E06\u0243\u0182\u0181]/g},
      {'base':'C', 'letters':/[\u0043\u24B8\uFF23\u0106\u0108\u010A\u010C\u00C7\u1E08\u0187\u023B\uA73E]/g},
      {'base':'D', 'letters':/[\u0044\u24B9\uFF24\u1E0A\u010E\u1E0C\u1E10\u1E12\u1E0E\u0110\u018B\u018A\u0189\uA779]/g},
      {'base':'DZ','letters':/[\u01F1\u01C4]/g},
      {'base':'Dz','letters':/[\u01F2\u01C5]/g},
      {'base':'E', 'letters':/[\u0045\u24BA\uFF25\u00C8\u00C9\u00CA\u1EC0\u1EBE\u1EC4\u1EC2\u1EBC\u0112\u1E14\u1E16\u0114\u0116\u00CB\u1EBA\u011A\u0204\u0206\u1EB8\u1EC6\u0228\u1E1C\u0118\u1E18\u1E1A\u0190\u018E]/g},
      {'base':'F', 'letters':/[\u0046\u24BB\uFF26\u1E1E\u0191\uA77B]/g},
      {'base':'G', 'letters':/[\u0047\u24BC\uFF27\u01F4\u011C\u1E20\u011E\u0120\u01E6\u0122\u01E4\u0193\uA7A0\uA77D\uA77E]/g},
      {'base':'H', 'letters':/[\u0048\u24BD\uFF28\u0124\u1E22\u1E26\u021E\u1E24\u1E28\u1E2A\u0126\u2C67\u2C75\uA78D]/g},
      {'base':'I', 'letters':/[\u0049\u24BE\uFF29\u00CC\u00CD\u00CE\u0128\u012A\u012C\u0130\u00CF\u1E2E\u1EC8\u01CF\u0208\u020A\u1ECA\u012E\u1E2C\u0197]/g},
      {'base':'J', 'letters':/[\u004A\u24BF\uFF2A\u0134\u0248]/g},
      {'base':'K', 'letters':/[\u004B\u24C0\uFF2B\u1E30\u01E8\u1E32\u0136\u1E34\u0198\u2C69\uA740\uA742\uA744\uA7A2]/g},
      {'base':'L', 'letters':/[\u004C\u24C1\uFF2C\u013F\u0139\u013D\u1E36\u1E38\u013B\u1E3C\u1E3A\u0141\u023D\u2C62\u2C60\uA748\uA746\uA780]/g},
      {'base':'LJ','letters':/[\u01C7]/g},
      {'base':'Lj','letters':/[\u01C8]/g},
      {'base':'M', 'letters':/[\u004D\u24C2\uFF2D\u1E3E\u1E40\u1E42\u2C6E\u019C]/g},
      {'base':'N', 'letters':/[\u004E\u24C3\uFF2E\u01F8\u0143\u00D1\u1E44\u0147\u1E46\u0145\u1E4A\u1E48\u0220\u019D\uA790\uA7A4]/g},
      {'base':'NJ','letters':/[\u01CA]/g},
      {'base':'Nj','letters':/[\u01CB]/g},
      {'base':'O', 'letters':/[\u004F\u24C4\uFF2F\u00D2\u00D3\u00D4\u1ED2\u1ED0\u1ED6\u1ED4\u00D5\u1E4C\u022C\u1E4E\u014C\u1E50\u1E52\u014E\u022E\u0230\u022A\u1ECE\u0150\u01D1\u020C\u020E\u01A0\u1EDC\u1EDA\u1EE0\u1EDE\u1EE2\u1ECC\u1ED8\u01EA\u01EC\u00D8\u01FE\u0186\u019F\uA74A\uA74C]/g},
      {'base':'OE','letters':/[\u00D6\u0152]/g},
      {'base':'OI','letters':/[\u01A2]/g},
      {'base':'OO','letters':/[\uA74E]/g},
      {'base':'OU','letters':/[\u0222]/g},
      {'base':'P', 'letters':/[\u0050\u24C5\uFF30\u1E54\u1E56\u01A4\u2C63\uA750\uA752\uA754]/g},
      {'base':'Q', 'letters':/[\u0051\u24C6\uFF31\uA756\uA758\u024A]/g},
      {'base':'R', 'letters':/[\u0052\u24C7\uFF32\u0154\u1E58\u0158\u0210\u0212\u1E5A\u1E5C\u0156\u1E5E\u024C\u2C64\uA75A\uA7A6\uA782]/g},
      {'base':'S', 'letters':/[\u0053\u24C8\uFF33\u1E9E\u015A\u1E64\u015C\u1E60\u0160\u1E66\u1E62\u1E68\u0218\u015E\u2C7E\uA7A8\uA784]/g},
      {'base':'T', 'letters':/[\u0054\u24C9\uFF34\u1E6A\u0164\u1E6C\u021A\u0162\u1E70\u1E6E\u0166\u01AC\u01AE\u023E\uA786]/g},
      {'base':'TZ','letters':/[\uA728]/g},
      {'base':'U', 'letters':/[\u0055\u24CA\uFF35\u00D9\u00DA\u00DB\u0168\u1E78\u016A\u1E7A\u016C\u01DB\u01D7\u01D5\u01D9\u1EE6\u016E\u0170\u01D3\u0214\u0216\u01AF\u1EEA\u1EE8\u1EEE\u1EEC\u1EF0\u1EE4\u1E72\u0172\u1E76\u1E74\u0244]/g},
      {'base':'UE','letters':/[\u00DC]/g},
      {'base':'V', 'letters':/[\u0056\u24CB\uFF36\u1E7C\u1E7E\u01B2\uA75E\u0245]/g},
      {'base':'VY','letters':/[\uA760]/g},
      {'base':'W', 'letters':/[\u0057\u24CC\uFF37\u1E80\u1E82\u0174\u1E86\u1E84\u1E88\u2C72]/g},
      {'base':'X', 'letters':/[\u0058\u24CD\uFF38\u1E8A\u1E8C]/g},
      {'base':'Y', 'letters':/[\u0059\u24CE\uFF39\u1EF2\u00DD\u0176\u1EF8\u0232\u1E8E\u0178\u1EF6\u1EF4\u01B3\u024E\u1EFE]/g},
      {'base':'Z', 'letters':/[\u005A\u24CF\uFF3A\u0179\u1E90\u017B\u017D\u1E92\u1E94\u01B5\u0224\u2C7F\u2C6B\uA762]/g},
      {'base':'a', 'letters':/[\u0061\u24D0\uFF41\u1E9A\u00E0\u00E1\u00E2\u1EA7\u1EA5\u1EAB\u1EA9\u00E3\u0101\u0103\u1EB1\u1EAF\u1EB5\u1EB3\u0227\u01E1\u01DF\u1EA3\u00E5\u01FB\u01CE\u0201\u0203\u1EA1\u1EAD\u1EB7\u1E01\u0105\u2C65\u0250]/g},
      {'base':'aa','letters':/[\uA733]/g},
      {'base':'ae','letters':/[\u00E4\u00E6\u01FD\u01E3]/g},
      {'base':'ao','letters':/[\uA735]/g},
      {'base':'au','letters':/[\uA737]/g},
      {'base':'av','letters':/[\uA739\uA73B]/g},
      {'base':'ay','letters':/[\uA73D]/g},
      {'base':'b', 'letters':/[\u0062\u24D1\uFF42\u1E03\u1E05\u1E07\u0180\u0183\u0253]/g},
      {'base':'c', 'letters':/[\u0063\u24D2\uFF43\u0107\u0109\u010B\u010D\u00E7\u1E09\u0188\u023C\uA73F\u2184]/g},
      {'base':'d', 'letters':/[\u0064\u24D3\uFF44\u1E0B\u010F\u1E0D\u1E11\u1E13\u1E0F\u0111\u018C\u0256\u0257\uA77A]/g},
      {'base':'dz','letters':/[\u01F3\u01C6]/g},
      {'base':'e', 'letters':/[\u0065\u24D4\uFF45\u00E8\u00E9\u00EA\u1EC1\u1EBF\u1EC5\u1EC3\u1EBD\u0113\u1E15\u1E17\u0115\u0117\u00EB\u1EBB\u011B\u0205\u0207\u1EB9\u1EC7\u0229\u1E1D\u0119\u1E19\u1E1B\u0247\u025B\u01DD]/g},
      {'base':'f', 'letters':/[\u0066\u24D5\uFF46\u1E1F\u0192\uA77C]/g},
      {'base':'g', 'letters':/[\u0067\u24D6\uFF47\u01F5\u011D\u1E21\u011F\u0121\u01E7\u0123\u01E5\u0260\uA7A1\u1D79\uA77F]/g},
      {'base':'h', 'letters':/[\u0068\u24D7\uFF48\u0125\u1E23\u1E27\u021F\u1E25\u1E29\u1E2B\u1E96\u0127\u2C68\u2C76\u0265]/g},
      {'base':'hv','letters':/[\u0195]/g},
      {'base':'i', 'letters':/[\u0069\u24D8\uFF49\u00EC\u00ED\u00EE\u0129\u012B\u012D\u00EF\u1E2F\u1EC9\u01D0\u0209\u020B\u1ECB\u012F\u1E2D\u0268\u0131]/g},
      {'base':'j', 'letters':/[\u006A\u24D9\uFF4A\u0135\u01F0\u0249]/g},
      {'base':'k', 'letters':/[\u006B\u24DA\uFF4B\u1E31\u01E9\u1E33\u0137\u1E35\u0199\u2C6A\uA741\uA743\uA745\uA7A3]/g},
      {'base':'l', 'letters':/[\u006C\u24DB\uFF4C\u0140\u013A\u013E\u1E37\u1E39\u013C\u1E3D\u1E3B\u017F\u0142\u019A\u026B\u2C61\uA749\uA781\uA747]/g},
      {'base':'lj','letters':/[\u01C9]/g},
      {'base':'m', 'letters':/[\u006D\u24DC\uFF4D\u1E3F\u1E41\u1E43\u0271\u026F]/g},
      {'base':'n', 'letters':/[\u006E\u24DD\uFF4E\u01F9\u0144\u00F1\u1E45\u0148\u1E47\u0146\u1E4B\u1E49\u019E\u0272\u0149\uA791\uA7A5]/g},
      {'base':'nj','letters':/[\u01CC]/g},
      {'base':'o', 'letters':/[\u006F\u24DE\uFF4F\u00F2\u00F3\u00F4\u1ED3\u1ED1\u1ED7\u1ED5\u00F5\u1E4D\u022D\u1E4F\u014D\u1E51\u1E53\u014F\u022F\u0231\u022B\u1ECF\u0151\u01D2\u020D\u020F\u01A1\u1EDD\u1EDB\u1EE1\u1EDF\u1EE3\u1ECD\u1ED9\u01EB\u01ED\u00F8\u01FF\u0254\uA74B\uA74D\u0275]/g},
      {'base':'oe','letters': /[\u00F6\u0153]/g},
      {'base':'oi','letters':/[\u01A3]/g},
      {'base':'ou','letters':/[\u0223]/g},
      {'base':'oo','letters':/[\uA74F]/g},
      {'base':'p','letters':/[\u0070\u24DF\uFF50\u1E55\u1E57\u01A5\u1D7D\uA751\uA753\uA755]/g},
      {'base':'q','letters':/[\u0071\u24E0\uFF51\u024B\uA757\uA759]/g},
      {'base':'r','letters':/[\u0072\u24E1\uFF52\u0155\u1E59\u0159\u0211\u0213\u1E5B\u1E5D\u0157\u1E5F\u024D\u027D\uA75B\uA7A7\uA783]/g},
      {'base':'s','letters':/[\u0073\u24E2\uFF53\u015B\u1E65\u015D\u1E61\u0161\u1E67\u1E63\u1E69\u0219\u015F\u023F\uA7A9\uA785\u1E9B]/g},
      {'base':'ss','letters':/[\u00DF]/g},
      {'base':'t','letters':/[\u0074\u24E3\uFF54\u1E6B\u1E97\u0165\u1E6D\u021B\u0163\u1E71\u1E6F\u0167\u01AD\u0288\u2C66\uA787]/g},
      {'base':'tz','letters':/[\uA729]/g},
      {'base':'u','letters':/[\u0075\u24E4\uFF55\u00F9\u00FA\u00FB\u0169\u1E79\u016B\u1E7B\u016D\u01DC\u01D8\u01D6\u01DA\u1EE7\u016F\u0171\u01D4\u0215\u0217\u01B0\u1EEB\u1EE9\u1EEF\u1EED\u1EF1\u1EE5\u1E73\u0173\u1E77\u1E75\u0289]/g},
      {'base':'ue','letters':/[\u00FC]/g},
      {'base':'v','letters':/[\u0076\u24E5\uFF56\u1E7D\u1E7F\u028B\uA75F\u028C]/g},
      {'base':'vy','letters':/[\uA761]/g},
      {'base':'w','letters':/[\u0077\u24E6\uFF57\u1E81\u1E83\u0175\u1E87\u1E85\u1E98\u1E89\u2C73]/g},
      {'base':'x','letters':/[\u0078\u24E7\uFF58\u1E8B\u1E8D]/g},
      {'base':'y','letters':/[\u0079\u24E8\uFF59\u1EF3\u00FD\u0177\u1EF9\u0233\u1E8F\u00FF\u1EF7\u1E99\u1EF5\u01B4\u024F\u1EFF]/g},
      {'base':'z','letters':/[\u007A\u24E9\uFF5A\u017A\u1E91\u017C\u017E\u1E93\u1E95\u01B6\u0225\u0240\u2C6C\uA763]/g}
];

if ($.fn.appendText) throw "InternalError: jquery appendText already exists";
$.fn.appendText = function(text) {
    return this.append(document.createTextNode(text));
};

function jqueryAppendMany(elt, list, separator) {
    if (!separator) separator = '';
    $.each(list, function (i, e) {
	if (i > 0) elt.append(separator);
	elt.append(e);
    });
    return elt;
}
function jqueryAppendManyText(elt, list, separator) {
    if (!separator) separator = '';
    $.each(list, function (i, e) {
	if (i > 0) elt.append(separator);
	elt.appendText(e);
    });
    return elt;
}
function appendWrappedText(elt, wrappedText) {
    return jqueryAppendManyText(elt, wrappedText.split(/\n+/), "<br>");
}
function spanFromList(list, separator){
    return jqueryAppendMany($("<span>"), list, separator);
}

function asciifie(s) {
    if (!s || $.isArray(s)) return s;
    $.each(diacriticsMap, function (i, v) {
	s = s.replace(v.letters, v.base);
    });
    return s;
}

function rejectEmpty(l) {
    return $.grep(l, function (v) { return v });
}

function array_includes(array, valueToFind) {
    return $.inArray(valueToFind, array) !== -1;
}

function partition(l, f) {
    var l1 = [];
    var l2 = [];
    $.each(l, function (i, e) {
	if (f(e)) l1.push(e); else l2.push(e);
    });
    return [l1, l2];
}

function flattenFailsafe(l) {
    var r = [];
    $.each(l, function (i, subl) {
	if (subl) $.merge(r, subl);
    });
    //console.log(r);
    return r;
}

function arraySubstraction(a, b) {
    return $.grep(a, function(e) {
        return b.indexOf(e) === -1;
    });
}

function capitalize (s) {
    return s.toLowerCase().replace(/(?:^|\s)\S/g, function(a) { return a.toUpperCase(); });
}

function formagtime(time) {
    var m = time.match(/([0-9]{4})([0-9]{2})([0-9]{2})[0-9]{6}Z?$/);
    return m ? m[3] + "/" + m[2] + "/" + m[1] : "(date invalide " + time + ")";
}

function leftPadZero(number, size) {
    number = number.toString();
    while (number.length < size) number = "0" + number;
    return number;
}

function round(number, precision) {
    return Math.round(number / precision) * precision;
}
function formatMB(n) {
    n = n / 1024 / 1024;
    // round it
    n = round(n, n >= 10 ? 1 : 0.1)
    return "" + n + "MB";
}
function formatDateRaw(d) {
    return leftPadZero(d.getDate(), 2) + "/" + leftPadZero(d.getMonth() + 1, 2) + "/" + d.getFullYear();
}

function formatDateTime(date) {
    var d = new Date(date);
    return "le " + formatDateRaw(d) + " à " + formatTimeHHhMM(d);
}
function formatTimeHHhMM(d) {
    return leftPadZero(d.getHours(), 2) + "h" + leftPadZero(d.getMinutes(), 2);
}

function formadate(epoch) {
    if (!epoch) return "(date inconnue)";
    var d = new Date(epoch * 24 * 3600 * 1000);
    return formatDateRaw(d);
}

function format_YYYYmmdd(date) {
    return date.replace(/^(\d\d\d\d)(\d\d)(\d\d)$/, "$3/$2/$1");
}

function formadelai(date1, date2) {
    var delai = date2 - date1;
    return delai < 90 ? Math.round(delai) + " jours" :
	delai < 1000 ? Math.round(delai /30.5) + " mois" :
	Math.round(delai/365.2421875) + " ans";
}

function timestamp_to_Date(timestamp) {
    var date = timestamp.replace(/(....)(..)(..)(..)(..)(.*)/, "$1-$2-$3T$4:$5:$6");
    return new Date(date);
}

function format_timestamp(timestamp) {
    var d = timestamp_to_Date(timestamp);
    var text = "le " + formatDateRaw(d) + " à " + formatTimeHHhMM(d);
    return $("<span>", { title: timestamp }).text(text);
}

function compute_MailDelivery(fwd, info) {
    var is_copy = $.inArray('mailbox', info.mailDeliveryOption) != -1;
    return (is_copy ? "copies vers " : "redirigée vers ") + 
	(fwd[0] === 'supannListeRouge' ? "une adresse mail" : fwd.join(", "));
}

function format_main_profile_info(info) {
    var elt = $("<span>").append($("<span>", { title: info.up1Priority }).text(info.up1Source)).appendText(
        (info.up1StartDate ? " du " + format_YYYYmmdd(info.up1StartDate) : '') + (info.up1EndDate ? " au " + format_YYYYmmdd(info.up1EndDate) : '')
    )
    if (info['info;x-demande']) elt.append($("<span>", { class: "info" }).text(" - motif: " + info['info;x-demande']));
    for (const uri of info['labeledURI'] || []) {
        const demande = uri.replace(/ {DEMANDE}$/, '')
        if (demande !== uri) elt.appendText(' - ').append(a_or_span(demande, 'détails demande')) 
    }
    if (info.computedFrom) elt.append($("<span>", { class: "info" }).text(" (profil partiel reconstruit à partir de " + info.computedFrom + ")"));
    return elt;
}

function format_main_profiles_info(profiles, info) {
    if (!info.accountStatus || info.accountStatus === "active") {
        function add_virtual_profile(up1Source, computedFrom) {
            profiles = profiles.concat([ { up1Source: up1Source, computedFrom: computedFrom } ])
        }
        if (info['supannEtuInscription-all'] && (info.eduPersonAffiliation || []).includes('student')) {
            add_virtual_profile('{APOGEE}student', 'la présence de supannEtuInscription')
        }
        if ((info.supannEtablissement || []).find(e => e.match(/\[PCU-ReseauPro\]$/)) &&
            (info.supannParrainDN || []).includes('supannCodeEntite=DGJC,ou=structures,dc=univ-paris1,dc=fr')) {
            add_virtual_profile('{RESEAUPRO}alum', 'supannEtablissement PCU-ReseauPro & parrain DPEIP-BAIP')
        }
        if (!profiles.length && (info.eduPersonAffiliation || []).includes('alum')) {
            add_virtual_profile('{GRACE}student', 'affiliation=alum & pas de profil')
        }
        if (!profiles.length && info.supannEmpId && !info.eduPersonPrimaryAffiliation) {
            add_virtual_profile('{GRACE}staff', "la présence de supannEmpId & pas d'affiliation & pas de profil")
        }
        if (!profiles.length && info.eduPersonPrimaryAffiliation === 'member' &&
            (info.supannParrainDN || []).includes('supannCodeEntite=DGE,ou=structures,dc=univ-paris1,dc=fr')) {
            add_virtual_profile('{SIHAM}entrant', 'primaryAffiliation member & parrain DRH')
        }
        if (!profiles.length) return "<i>aucun (étrange)</i>"
    }
    return profiles.length ? spanFromList(profiles.map(format_main_profile_info), "<br>") : ""
}

function compute_supannRoles(info) {
    var span = $("<span>");
    var done = {};
    if (info['supannRoleEntite-all']) {
	$.each(info['supannRoleEntite-all'], function (i, role) {
	    if (!$.isEmptyObject(done)) span.append("<br>");
	    done[role.role] = true;
	    span.appendText(role.role + " (");
	    span.append(format_supannCodeEntite([role['structure']]));
	    span.appendText(")");
	});
    }
    return !$.isEmptyObject(done) && span;
}

function format_supannActivite(all, fInfo) {
    var format_it = function (e) {
        return $("<span>", { title: e.key }).text(e.name);
    };
    var cat2l = {}
    for (const e of all) { 
        const cat = 
            e.key.match(/^{REFERENS}/) ? 'REFERENS' :
            e.key.match(/^{UAI:0751717J:RIFSEEP}/) ? 'RIFSEEP' :
            e.key.match(/^{CNU}/) ? 'CNU' :
            e.key.match(/^{UAI:0751717J:ACT}/) ? undefined : // NB: ignore it since we display "description" and this is a duplicate added here by wsgroups, cf https://projets.univ-paris1.fr/project/22/task/135. 
            'various'
        if (cat) {
            if (!cat2l[cat]) cat2l[cat] = []
            cat2l[cat].push(e)
        }
    }
	$.each(cat2l, function (cat,l) {
        fInfo['supannActivite-' + cat] = spanFromList($.map(l, format_it), '<br>');
    })
}

function format_supannEmpExtProfilAll(l) {
    var format_it = function (e) {

        return e.population?.name + " (" + eduPersonAffiliation_valnames[e.affil] + ")" +
            (e.affect ? ", affecté à " + e.affect.name + (e.etab ? " (" + e.etab.name + ")" : '') : 
                (e.etab ? ", affecté à " + e.etab.name : '')) +
            (e.parrain ? ", parrainé par " + e.parrain.name : '')
    }
    return spanFromList($.map(l, format_it), "<br>")
}

function format_supannEtuInscriptionAll(all, fInfo) {

    var anneeinsc = Math.max.apply(null, $.map(all, function (e) { return e.anneeinsc }));
    var ll = partition(all, function (e) { return e.anneeinsc == anneeinsc });
    var curr = ll[0], prev = ll[1];

    var format_it = function (e) { 
	var text = e.etape + " (" + e.anneeinsc + ")";
	var title = [];
	$.each(e, function (k,v) { 
	    if (k !== 'etape') title.push(k + ": " + v);
	});
	return $("<span>", { title: title.join("\n") }).text(text); 
    };
    if (curr.length)
	fInfo['supannEtuInscription-all'] = spanFromList($.map(curr, format_it), "<br>");
    if (prev.length) 
	fInfo['supannEtuInscription-prev'] = spanFromList($.map(prev, format_it), "<br>");
}

const compareLowerCased = (field) => (a, b) => (
	a[field].toLowerCase().localeCompare(b[field].toLowerCase())
) 

function format_memberOf(all) {
    all = all.sort(compareLowerCased("key"));
    var moreInfo = get_groupsMoreInfo(all);
    return spanFromList($.map(all, function (e) {
	    var span = $("<span>").append(a_or_span(e.name && e.name.match(/:/) && toGrouperUrl(e.key), e.key))
                .appendText(" : " + (e.description || ''));
        if (moreInfo[e.key]) span.append(moreInfo[e.key]);
        return span;
    }), "<br>");
}

function format_shadowExpire(val, info) {
    var today = todayEpochDay();
    if (val <= today) {
        return formadate(val) + " (" + important("EXPIRE") + ")";
    } else {
        var delai = formadelai(today, val);
        if (delai === "6 mois" && (info.up1Profile || []).find(function (p) { return p.up1Source.match(/^\{HARPEGE\}/) })) {
            return "date inconnue (compte prolongé au fur et à mesure)";
        }
        return formadate(val) + " (dans " + delai + ")";
    }
}

function format_supannRefId(val) {
    return val.map(id => {
        let [, prefix, id_] = id.match(/\{(.*)\}(.*)/)
        prefix = prefix.replace('UAI:0752707K', 'CUJAS')
        // hide refIds already displayed via supannEmpId/up1TagMifare
        return ["HARPEGE", "MIFARE", "SIHAM"].includes(prefix) ? '' : prefix + " : " + id_
    }).filter(s => s).sort().join("<br>")
}

function addYears(date, years) {
    var r = new Date(date);
    r.setFullYear(r.getFullYear() + years)
    return r;
}
    
function is_not_adult(timestamp) {
    var birthDate = timestamp_to_Date(timestamp);
    return birthDate && addYears(birthDate, 15) >= new Date();
}

function compute_Person(info) {
    var displayName = info.displayName || info.sn && info.givenName && info.givenName + " " + info.sn || "<INCONNU>";
    var person = (info.supannCivilite ? info.supannCivilite + " " : '') + displayName;

	var birthName = info.up1BirthName;
	if (birthName === info.sn) birthName = '';
	if (birthName || info.up1BirthDay) {
	    var civilite = info.supannCivilite || '';
	    var nee = civilite === "M." ? "né" :
		civilite.match(/Mme|Mlle/) ? "née" : "né(e)";
	    person = person + ", " + nee +
		(birthName ? " " + birthName : '') +
		(info.up1BirthDay ? " le " + formagtime(info.up1BirthDay) : '')
	}

    var Person = $("<span>").text(person); 
    if (info.up1BirthDay && is_not_adult(info.up1BirthDay)) {
        Person.append(" " + important(civilite.match(/Mme|Mlle/) ? "MINEURE" : "MINEUR", "moins-de-15-ans"));
    }
	Person.attr('title', 'Prénom : ' + info.givenName + (info.up1AltGivenName ? " (" + info.up1AltGivenName.join(' ') + ")" : '') + "\nNom : " + info.sn + "\nComplet : " + info.cn);
    return Person;
}

function compute_Identifiers(info) {   
    var span = $("<span>");
    $.each(parse_attrs_text(sub_attrs_labels.Identifiers), function (i, e) {
	var v = info[e.attr];
	if (v) {
	    if (e.text) span.appendText(e.text + ": ");
	    if (e.attr === 'supannEtuId') {
		span.append(a_or_span(apogeeStudentDetailUrl + v, v)).appendText(" ");
	    } else {
		span.appendText(v + " ");
	    }
	}
    });
    return span;
}

function todayEpochDay() {
    return new Date().getTime() / 1000 / 3600 / 24;
}

function formatValues(valnames, v) {
    if ($.isArray(v)) {
	var fv = rejectEmpty($.map(v, function (val) { return valnames[val] }));
	return fv.length > 0 ? spanFromList(fv, ", ") : null;
    } else if (valnames[v]) {
	return spanFromList([valnames[v]]);
    } else {
	return;
    }
}

function formatSomeUserValues(info, fInfo) {
    $.each(info, function (attr, v) {
	var valnames = attr2valnames[attr];
	if (valnames) {
	    var fv = formatValues(valnames, v);
	    if (fv) fInfo[attr] = fv;
	}
    });
}

function formatLastLogins(info, data, div) {
    var since = data.since;
    var list = data.list.reverse().slice();
    var lastErrs = [];
    while (list.length && list[0].error) {
	lastErrs.push(list.shift());
    }
    if (list.length) {
	div.text(", dernier login CAS " + formatDateTime(list[0].when));
	if (lastErrs.length)
	    div.append(important(", " + lastErrs.length + " échecs depuis"));
    } else if (lastErrs.length) {
	div.append(", " + important(lastErrs.length + " login en échecs depuis " + formatDateTime(lastErrs.reverse()[0].when)));
    } else {
	if (info.accountStatus === "active" && !info.isRole) 
	    div.text(", aucune tentative de login depuis " + since.map(formatDateTime).join(' ou '));
    }
    if (data.list.length) {
	var details = $("<div class='vertical-scroll hidden'>");
	$.each(data.list, function (v, e) {
	    var t = formatDateTime(e.when) + " : " + (e.error || "SUCCESS") + " <small>(login = " + e.login + ", ip = " + e.ip + " )</small>";
	    details.append(t + '<br>');
	})
	if (data.fuzzy_failed.length) {
	    details.append("<br>Logins en échec avec login légèrement différent :<br>");
	}
	$.each(data.fuzzy_failed, function (v, e) {
	    var t = formatDateTime(e.when) + " : " + e.login + " FAILED <small>(ip = " + e.ip + " )</small>";
	    details.append(t + '<br>');
	})
	div.append($("<span class='clickable'>").append(" <small>details</small>").click(function () { details.toggleClass("hidden") }));
	div.append(details);
    }
}

function format_one_kerberosInfo(info, principal, krb) {
    var txt = [ $("<span>", { title: principal }).text("KERBEROS") ];
	
    var txtPassword = [];
    if (krb.lastmod) txtPassword.push("changé le " + formatDateTime(krb.lastmod * 1000));
    if (krb.expire) {
	krb.expire = krb.expire / 24 / 3600;
	if (krb.expire != info.shadowExpire) txtPassword.push("expire le " + formadate(krb.expire));
    } else {
	if (info.expire) txtPassword.push(important("SANS EXPIRATION"));
    }
    if (txtPassword.length) {
        const elt = $("<span>").appendText("mot de passe " + txtPassword.join(", "))
        const one_day = 24 * 60 * 60 * 1000;
        if (info.allowExtendedInfo >= 2 && krb.lastmod && krb.lastmod * 1000 > Date.now() - 7 * one_day) {
            elt.appendText(" ").append($('<small><a target="_blank" href="' + baseURL + '/userLastModifications.php?uid=' + info.uid + '">modifications des 7 derniers jours</a></small>'))
        }
        txt.push(elt);
    }
    if (krb.locked) {
	if (info.accountStatus != 'noaccess') txt.push(important("VERROUILLE"));
    } else {
	if (info.accountStatus == 'noaccess') txt.push(important("NON VERROUILLE"));
    }
    return spanFromList(txt, ", ");
}
    
function format_kerberosInfo(info, auth, div) {
    var kerberos = auth && auth.kerberos;
    $.each(info.up1KrbPrincipal, function (i, principal) {
	var krb = kerberos[principal];
	if (krb) {
	    div.append(format_one_kerberosInfo(info, principal, krb)).append('<br>');
	} else {
	    div.append("Approbation inter-domaine KERBEROS " + principal + important(" ??")).append('<br>');
	}
    });
}

function format_lists_one(div, slists, isMainMail) {
    var warnings = {
        reception_nomail: { level: 'important', text: "Réception désactivée" },
        reception_various: { level: 'notice', text: "Réception modifiée" },
        bounce30: { level: 'notice', text: "Problèmes d'envoi" },
        bounce50: { level: 'important', text: "Envoi en échec" },
        suspend: { level: 'important', text: "Abonnement suspendu" },
        since7: { level: 'notice', text: "Abonnement récent" },
        since1: { level: 'important', text: "Abonné aujourd'hui" },
        none: { level: '', text: "Pas de warning" },
    };
    slists.forEach(function (opts) {
        var has_warning = false;
        function add(warning) {
            if (!warnings[warning]) warnings[warning] = { level: 'notice', text: warning };
            if (!warnings[warning].l) warnings[warning].l = [];
            warnings[warning].l.push(opts.name);
            has_warning = true;
        }
        
        if (!isMainMail) add(opts.mail);
        if (opts.reception) {
            if (opts.reception === 'nomail') add('reception_nomail');
            else if (!array_includes(['mail', 'default', 'urlize'], opts.reception)) add('reception_various');
        }
        if (opts.bounce && opts.bounce > 50) add('bounce50');
        else if (opts.bounce && opts.bounce > 30) add('bounce30');
        if (opts.suspend) add('suspend');
        if (opts.since) {
            var delta_days = (new Date().getTime() / 1000 - opts.since) / 3600 / 24;
            if (delta_days < 1) add('since1');
            else if (delta_days < 7) add('since7');
        }
        if (!has_warning) add('none');
    });
    var ul = $("<ul>");
    Object.keys(warnings).forEach(function (warning) {    
        var e = warnings[warning];
        if (e.l) {
            var li = $('<li>');
            if (e.level) {
                li.append(important(e.text, undefined, e.level));
                li.appendText(" : " + e.l.join(', '));
            } else {
                li.appendText(e.text + " : ");
                li.append($("<span>", { title: e.l.join(', ') }).appendText(e.l.length + " listes "));
            }
            ul.append(li);
        }
    });
    div.append("<b>" + (isMainMail ? "Avec l'adresse mél de l'établissement" : "Avec une autre adresse") + "</b> : ");
    div.append(ul);
    div.append("<p>");
}
function format_lists(info, lists, div) {
    var withMainMail = [];
    var withoutMailMail = [];
    $.each(lists, function (mail, slists) { 
        var o = (mail === (info.mail || "").toLowerCase() ? withMainMail : withoutMailMail);
        Object.keys(slists).sort().forEach(function (name) {
            var opts = slists[name];
            opts.mail = mail;
            opts.name = name;
            o.push(opts);
        });
    });
    if (withMainMail.length) format_lists_one(div, withMainMail, true);
    if (withoutMailMail.length) format_lists_one(div, withoutMailMail, false);
}

function format_quota(quota) {
    var size = quota.size;
    var txt = '';
    var grace = size.grace && size.grace /24 / 3600;
    if (size.blocked) {
        txt = important("BLOQUÉE", "mailbox-blocked");
        if (grace && grace <= todayEpochDay()) txt = txt + important(" depuis le " + formadate(grace));
    } else if (grace && grace > todayEpochDay()) {
        txt = important("delai de grâce restant : " + formadelai(todayEpochDay(), grace), "mailbox-grace");
    }
    var percent = Math.round(size.used / size.soft * 100) + "%";
    if (size.used >= size.soft) percent = important(percent);
    txt = (txt ? txt + "<br>" : '') + "Taux d'occupation " + percent;
    txt = txt + " (" + formatMB(size.used * 1024) + " / " + formatMB(size.soft * 1024) + ")";
    return txt;
}

function format_mailboxINBOX(mbox) {
    return mbox.recent + " courriels reçus depuis la dernière consultation";
}

function format_mailboxFilters(filter) {
    if (filter && filter.rules) {
	return "Filtres présents (" + filter.rules + " règles)";
    } else {
	return "Aucun filtre";
    }
}
    
function format_mailboxInfo(mailboxes, infoDiv) {
    infoDiv.empty();
    if (!mailboxes) {
        infoDiv.appendText("aucune");
        return;
    }
    $.each(mailboxes, function (server, mailbox) {
	if (mailbox.quota) infoDiv.append(format_quota(mailbox.quota));
	infoDiv.append("<br>" + format_mailboxINBOX(mailbox.status && mailbox.status.INBOX));
	infoDiv.append("<br>" + format_mailboxFilters(mailbox.filter));
	if (mailbox.filter && mailbox.filter.vacation) infoDiv.append("<br>Répondeur activé");
    });
}

function format_folderInfo(folder, infoDiv) {
    infoDiv.empty();
    if (!folder) {
        infoDiv.appendText("Taux d'occupation non disponible");
    } else if (folder.quota) {
        infoDiv.append(format_quota(folder.quota));
    }
}

function format_groupMoreInfo(info) {
    if (!info) return "";
    var s = info.path + (info.quota ? "\n" + format_quota(info.quota) : '');
    console.log(s);
    return s;
}
    
function get_lastLogins(info) {
    var infoDiv = $("<span>");
    asyncInfoRaw(lastLoginsUrl, { login: info.supannAliasLogin || info.uid, mail: info.mail }, infoDiv, function (data) {
	    if (data.length == 0) {
		infoDiv.text("user not found (??)");
	    } else if (data.length > 1 && 0) {
		infoDiv.text("internal error (multiple user found)");
	    } else {
		infoDiv.empty().append(formatLastLogins(info, data, infoDiv));
	    }
    });
    return infoDiv;
}
    
function get_kerberosInfo(info, infoDiv) {
    asyncInfoRaw(moreInfoUrl, { uid: info.uid, info: "auth" }, infoDiv, function (data) {
	    var moreInfo = data && data[info.uid];
	    if (!moreInfo) {
		infoDiv.text("user not found (??)");
	    } else {
		infoDiv.empty().append(format_kerberosInfo(info, moreInfo.auth, infoDiv));
	    }
    });
}
    
function get_lists(info) {
    var infoDiv = $("<span>...</span>");
    asyncInfoRaw(moreInfoUrl, { uid: info.uid, info: "lists" }, infoDiv, function (data) {
	    var moreInfo = data && data[info.uid];
	    if (!moreInfo) {
		infoDiv.text("user not found (??)");
	    } else {
		infoDiv.empty().append(format_lists(info, moreInfo.lists, infoDiv));
	    }
    });
    return infoDiv;
}

function get_Responsable(info) {
    var infoDiv = $("<span>...</span>");
    asyncInfoRaw(getGroupURL, { CAS: true, key: "structures-" + info.supannEntiteAffectationPrincipale, attrs: 'roles' }, infoDiv,
       function (data) {
	    infoDiv.empty();
	    if (!data || !data.key) {
		infoDiv.text("group not found (??)");
	    } else if (data.roles.length == 0) {
		infoDiv.text("inconnu");
	    } else {
		$.each(data.roles, function (i, user) {
		    infoDiv.append($("<a>", { href: '#' + encodeURIComponent(user.uid) }).text(user.displayName));
		    infoDiv.appendText(" (" + user.supannRoleGenerique.join(', ') + ")");
		    infoDiv.append("<br>");
		});
	    }
       });
    return infoDiv;
}

function get_mailbox_folder_Info(info, fInfo) {
    var infoDiv = fInfo.Mailbox = $("<div>...</div>");
    fInfo.Folder = $("<div>").text(info.sambaHomePath ? '...' : '');
    asyncInfoRaw(moreInfoUrl, { uid: info.uid, info: "mailbox,folder", type: info.isRole ? "role" : "user" }, infoDiv, function (data) {
	    var moreInfo = data && data[info.uid];
	    if (moreInfo && moreInfo.mailbox) {
		format_mailboxInfo(moreInfo.mailbox, infoDiv);
	    } else {
	        infoDiv.text("aucune");
		['mail', 'mailAlternateAddress'].forEach(function (attr) {
	            if (fInfo[attr]) fInfo[attr].append($("<span class='notice'> (mail non actif)</span>"));
		})
	    }
	    if (moreInfo) {
		if (moreInfo.folder || info.sambaHomePath) format_folderInfo(moreInfo.folder, fInfo.Folder);
	    }
    });
}

function get_groupsMoreInfo(groups) {
    var r = {};
    groups.forEach(function (group) {
        if ($.inArray('up1NasGroup', group.objectClass) !== -1) {
            r[group.key] = $("<span title='en cours...'>📁</span>");
        }
    });
    var needed_ids = Object.keys(r);
    if (needed_ids.length) {
      var forError = r[needed_ids[0]];
      asyncInfoRaw(baseURL + "/groupMoreInfo", { ids: needed_ids.join(',') }, forError, function (data) {
        $.each(data, function (id, info) {
            var elt = r[id];
            elt.prop('title', format_groupMoreInfo(info.folder));
        });
      });
    }
    return r;
}
    
function get_Applications(info, fInfo) {
    var infoDiv = $("<div>...</div>");
    fInfo.Applications = $("<div>").append(infoDiv).append("<a target='_blank' href='" + impersonateUrl + "#" + info.uid + "'>voir l'ENT de l'utilisateur</a>");

    asyncInfoRaw(appsUrl, { uid: info.uid }, infoDiv, function (data) {
        const apps = data.layout.folders.flatMap(e => e.portlets).sort(compareLowerCased("fname"))
        infoDiv.empty();
        infoDiv.append(spanFromList(apps.map(e => $("<span>", e).text(e.fname)), " "))
    });
}

function asyncInfoRaw(url, params, infoDiv, success) {
    $.ajax({
	url: url,
	dataType: "jsonp",
	crossDomain: true, // needed if url is CAS-ified or on a different host than application using autocompleteUser
	data: params,
	error: function () {
	    infoDiv.text("Erreur web service");
	},
	success: success
    });
}

function dn2uid(dn) {
    return dn.replace(/uid=(.*?),.*/, "$1");
}

function compute_Account_and_accountStatus(info, fInfo) {
    if (info.up1KrbPrincipal) {
	fInfo.Account = spanFromList($.map(info.up1KrbPrincipal, function (principal) {
	    return format_one_kerberosInfo(info, principal, {});
	}), '');
	if (info.allowExtendedInfo >= 1) {
	    get_kerberosInfo(info, fInfo.Account);
	}
    } else {
	if ( (!info.shadowExpire || info.shadowExpire > todayEpochDay()) && !info.accountStatus) {
	    fInfo.accountStatus = spanFromList([important('NON ACTIVE', 'status-non-active')]);
	}
	if (info.shadowLastChange) {
	    fInfo.Account = (info.isRole ? '' : important("LDAP", 'non-kerberos') + ", ") + "mot de passe changé le " + formadate(info.shadowLastChange);
	} else if (info.isRole) {
        fInfo.Account = "accès en délégation seule";
    }
    }
    if (!info.accountStatus || info.accountStatus === "active") {
	if (!info.eduPersonAffiliation && !info.isRole)
	    fInfo.accountStatus.append(" (" + important('il manque eduPersonAffiliation', 'no-affiliation', 'info') + ")");
    }
    if (info.allowExtendedInfo > 1)
	fInfo.accountStatus.append(get_lastLogins(info));

    if (fInfo.shadowFlag) {
        fInfo.accountStatus.append(" (").append(fInfo.shadowFlag);
        if (info['seeAlso-all']) {
            fInfo.accountStatus.append(" de ");
            info['seeAlso-all'].forEach(function (e) {
                var uid = dn2uid(e.key);
                fInfo.accountStatus.append($("<a>", { href: '#' + encodeURIComponent(uid) }).text(e.name));
            });
        }
        fInfo.accountStatus.append(")");
    }
}

var role2text = {
    "manager": "responsable",
    "roleOccupant": "titulaire",
    "member": "membre",
    "secretary": "suppléant"
};

function format_up1Roles(val) {
    var mail2roles = {};
    var mail2descr = {}
    $.each(val, function (i, e) {
	if (!mail2roles[e.mail]) mail2roles[e.mail] = []
	mail2roles[e.mail].push(role2text[e.role] || e.role);
	mail2descr[e.mail] = e.seeAlso || e.description;
    });
    return spanFromList($.map(mail2roles, function (roles, mail) {
	return $("<span>", { title: mail2descr[mail] })
	    .append($("<a>", { href: "#" + mail }).text(mail))
	    .appendText(" (" + roles.join(", ") + ")");
    }), "<br>");
}

function format_members(val) {
    return spanFromList($.map(val, function (member) {
        var match_cn = member.key.match(/^cn=(.*?),ou=groups/);
        var a = match_cn ?
                    a_or_span(member.name && member.name.match(/:/) && toGrouperUrl(match_cn[1]), match_cn[1]) :
                    a_or_span('#' + encodeURIComponent(dn2uid(member.key)), member.name);
        return $("<span>").append(a).appendText(member.description ? " (" + member.description + ")" : '');
    }), "<br>");
}

function format_members_not_admin(val, info) {
    var admins = (info['supannGroupeAdminDN-all'] || []).map(member => member.key)
    var val_ = val.filter(member => !admins.includes(member.key))
    return format_members(val_)
}

function format_supannCodeEntite(l, principale) {
    return spanFromList(rejectEmpty($.map(l, function (e) {
	if (e.name || e.key) {
	    var title = (e.description || '') + " (" + e.key + ")";
	    var elt = a_or_span(e.labeledURI, e.name || e.key).attr('title', title);
	    if (principale && e.key === principale && l.length > 1) elt = $("<b>").append(elt);
	    return elt;
	} else
	    return null;
    })), ", ");
}
function format_supannEntiteAffectation(l, info) {
    var principale = info['supannEntiteAffectationPrincipale'];
    var elt = format_supannCodeEntite(l, principale);
    var err;
    if (!principale) {
        if (info.eduPersonPrimaryAffiliation !== 'student')
	    err = important("affectation principale manquante");
    } else if ($.grep(l, function (e) { return e.key === principale }).length === 0) {
	err = important("affectation principale " + principale + " non listée");
    }
    if (err) elt.appendText(l && l.length ? ", " : '').append(err);
    return elt;
}

function format_mail(mail, displayName) {
    var dest = displayName ? displayName + " <" + mail + ">" : mail;
    return $("<a>", { href: 'mailto:' + encodeURIComponent(dest) }).text(mail);
}

function format_telephoneNumber(number, attr) {
    var linkName = attr == 'facsimileTelephoneNumber' ? 'fax' : 'tel';
    var format_it = function (number) {
	var number_ = number.replace(/^0([67])(\d\d)(\d\d)(\d\d)(\d\d)$/, '+33 $1 $2 $3 $4 $5');
	var link = number_.replace(/[^0-9+]/g, '');
	var a = $("<a>", { href: linkName + ':' + link }).text(number_);
	a.attr('title', attr + ": " + number);
	return a;
    };
    if ($.isArray(number)) {
	return spanFromList($.map(number, format_it), ", ");
    } else {
	return format_it(number);
    }
}

function a_or_span(href, text) {
    var node = href ? $("<a>", { href: encodeURI(href) }) : $("<span>");
    return node.text(text);
}

function format_link(link) {
    return a_or_span(link, link);
}

function formatUserInfo(info, showExtendedInfo) {
    //if (info.roomNumber) info.postalAddress = info.roomNumber + ", " + info.postalAddress;

    if (!info.up1Profile && !info.up1Source) info.up1Profile = []; // force displaying even if empty to handle virtual profiles
    
    var fInfo = {};

    formatSomeUserValues(info, fInfo);

	fInfo.Identifiers = info.supannAliasLogin && info.supannAliasLogin !== info.uid ? info.supannAliasLogin + ", uid: " + important(info.uid) : info.uid;
	fInfo.OtherIdentifiers = compute_Identifiers(info);

    
    fInfo.Person = compute_Person(info);
    $.each(simple_formatters, function (attr, formatter) {
        if (info[attr]) fInfo[attr] = formatter(info[attr], info);
    });
    if (info['supannEtuInscription-all']) format_supannEtuInscriptionAll(info['supannEtuInscription-all'], fInfo);
    if (info['supannActivite-all']) format_supannActivite(info['supannActivite-all'], fInfo);
	// if we have up1BirthDay, we have full power
	if (!info.up1Source) compute_Account_and_accountStatus(info, fInfo);
    if (info.supannEntiteAffectationPrincipale) fInfo.Responsable = get_Responsable(info);
    fInfo.supannRoles = compute_supannRoles(info);

    $.each(['telephoneNumber', 'facsimileTelephoneNumber', 'supannAutreTelephone', 'mobile', 'pager'], function (i, attr) {
	if (info[attr]) fInfo[attr] = format_telephoneNumber(info[attr], attr);
    });
    $.each(['mail', 'mailAlternateAddress', 'supannAutreMail', 'supannMailPerso'], function (i, attr) {
	if (info[attr]) fInfo[attr] = format_mail(info[attr], info.displayName);
    });

    if (info.allowExtendedInfo >= 1) get_mailbox_folder_Info(info, fInfo);

    if (showExtendedInfo >= 2) fInfo.Listes = get_lists(info);

    if (info.allowExtendedInfo >= 1 && !info.isRole) fInfo["Photo"] = "<img src='" + userphotoUrl + "?uid=" + info.uid +
        (showExtendedInfo >= 2 ? "&app-cli=userinfo" : "") + "'>";

    if (info.accountStatus === "active" && info.allowExtendedInfo >= 1 && !info.isRole) get_Applications(info, fInfo)

    return fInfo;
}

const app = Vue.createApp({
 components: {
   Affiliation: {
    props: [ 'primary', 'affiliations', 'user_info' ],
    computed: {
        otherText() {
            const other = arraySubstraction(this.affiliations, [this.primary, 'member'])
            return other.map(aff => this.toText[aff] || aff).join(', ')
        },
        toText() { return eduPersonAffiliation_valnames },
        up1Sources() { return this.user_info?.up1Profile?.map(p => p.up1Source) },
    },
    template: `<span>
        <span v-if="primary === 'member' || !primary">
            <Important :s="primary || 'MANQUANTE'" helpPage='no-precise-affiliation'></Important>
        </span>
        <span v-else>
            {{toText[primary] || primary}}
        </span>
        <span v-if="otherText">
            (secondaires: {{otherText}})
        </span>
        <span v-if="!affiliations.includes('member')">
            (non membre)
        </span>
        <span v-if="primary === 'staff' && up1Sources?.includes('{HARPEGE}heberge')">
            <Important s=" hébergé"></Important>
        </span>
    </span>`,
   },
   finfo: {
    props: [ 'val', 'elt' ],
    template: '<span></span>',
    mounted: function () {
        if (this.elt) {
            $(this.$el).append(this.elt);
        } else if (typeof this.val === "string") {
            $(this.$el).text(this.val);
        } else if ($.isArray(this.val)) {
            $(this.$el).text(this.val.join(', '));
        } else {
            throw "InternalError";	       
        }
    },
  },
 },
    data: function () { return {
        allowInvalidAccounts: false,
        currentUser: undefined,
        allowExtendedInfo: undefined,
        showExtendedInfo: 1,
        result: { msg: undefined, info: {} },
        selectedProfile: undefined,
        user_fInfo: {},
    } },
    mounted: function() {
        this.install_autocompleteUser();
        this.install_clickedTitle();
        
        var that = this;
        this.useHashParam();
        $(window).on('hashchange', function () { that.useHashParam() });

    },
    computed: {
        main_attrs_labels: function () {
            return main_attrs_labels.map(parse_attrs_text)
        },
        user_info: function () {
            var that = this;
            return this.selectedProfile ? this.result.info.up1Profile.find(function (profile) { return profile.up1Source === that.selectedProfile }) : this.result.info; 
        },
        baseURL: () => baseURL,
    },
    watch: {
        'allowInvalidAccounts': function () {
            this.install_autocompleteUser();
            this.asyncInfo();
        },
        'currentUser': function () {
            console.log('currentUser is now ', this.currentUser.value);
            this.asyncInfo();
        },
        'showExtendedInfo': function () {
            console.log('showExtendedInfo is now ', this.showExtendedInfo);
            this.asyncInfo();
        },
        'user_info': function () {
            this.user_fInfo = this.user_info ? formatUserInfo(this.user_info, this.showExtendedInfo) : {};
        },
    },
    methods: {
        useHashParam: function () {
            var value = document.location.hash && document.location.hash.replace(/^#/, '');
            value = value.replace(/[&?]ticket=.*/, '')
            value = value.replace(/[&?]auth_checked/, '')
            location.hash = value
            if (value && (!this.currentUser || this.currentUser.value != value)) {
                this.currentUser = { label: value, value: value };
            }
        },
        text: function (msg, info) {
            if (info) {
                info.isRole = (info.objectClass || []).includes('up1Role');
            }
            this.result = { msg: msg, info: info };
        },
        asyncInfo: function () {
            var user = this.currentUser;
            console.log('asyncInfo', user.label);
            this.text("Vous avez selectionné " + user.label + ". Veuillez patienter...");
            // to be able to bookmark users
            window.location.hash = '#' + user.value;
            this.selectedProfile = '';
     
            var wsParams = {
                id: user.value,
                showErrors: this.showExtendedInfo,
                allowInvalidAccounts: 'all',
                allowRoles: true,
                showExtendedInfo: this.showExtendedInfo
            };
            var that = this;
            asyncInfoRaw(searchUserURL, wsParams, this, function (data) {
                if (data.length > 1) {
                    var sub = data.filter(function (u) { return user.value === u.uid });
                    if (sub.length) data = sub;
                }
                if (data.length == 0) {
                    that.text("user not found (??)");
                } else if (data.length > 1) {
                    that.text("internal error (multiple user found)");
                } else {
                    that.allowExtendedInfo = data[0].allowExtendedInfo = data[0].globalInfo?.allowExtendedInfo;
                    that.text('', data[0]);
                }
            });
        },
        install_autocompleteUser: function () {
            console.log("install_autocompleteUser", this.allowInvalidAccounts);
            var that = this;
            var select = function (event, ui) {
                $(this).blur(); // important to close virtual keyboard on mobile phones
                if (ui.item.value === 'unknown') {
                    // gasp, no mail, forcing wantedAttr "uid"
                    ui.item.value = ui.item.uid;
                }
                that.currentUser = ui.item;
                return false;
            };
            var input = $(".token-autocomplete input");

            // remove previous
            input.autocompleteUser_remove();

            input.autocompleteUser(searchUserURL, { 
                select: select, disableEnterKey: true, 
                wantedAttr: 'mail', // mail is best attr to do a further searchUser to get all attrs
                wsParams: { showExtendedInfo: true, showErrors: this.allowInvalidAccounts, allowNoAffiliationAccounts: true, allowInvalidAccounts: this.allowInvalidAccounts && 'all' || undefined },
            });
            input.attr('placeholder', 'Nom prénom'); // why?
            input.handlePlaceholderOnIE();
        },
        install_clickedTitle: function () {
            $(".annuaireResult").on('click', "span[title]", function (evt) {
                if (evt.target !== this) return; // only on span
                var $title = $(this).find(".clickedTitle");
                if (!$title.length) {
                    $(this).append($('<span>', { 'class': "clickedTitle" }).text($(this).attr("title")));
                } else {
                    $title.remove();
                }
            });        
        },
    },
})

app.component('Important', {
    props: ['s', 'helpPage', 'className'],
    computed: {
      helpUrl() { return helpUrl.replace('HELP_ID', this.helpPage) },
    },
    template: `<span>
      <span :class='className || "important"'>{{s}}</span>
      <a v-if="helpPage" :href='helpUrl' 
         target='form_help' class='help'
         onclick='window.open("", "form_help", "toolbar=no,scrollbars=yes,alwaysRaised,width=1000,height=400")'
      >?</a>
    </span>`,
})

$.ajax({
    url: baseURL + "/login?CAS=MFA",
    dataType: "jsonp",
    crossDomain: true, // needed if url is CAS-ified or on a different host than application using autocompleteUser
    success: function (data) {
        if (!data.USER) {
            console.log("wsgroups autologin failed, forcing a redirect to", data.LOGIN_URL)
            document.location = data.LOGIN_URL + '?service=' + encodeURIComponent(document.location.href)
        }
    },
});

app.mount('#annuaire');

