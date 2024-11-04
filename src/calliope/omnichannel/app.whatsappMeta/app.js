'use strict';

const 
  request = require('request'),
  express = require('express'),
  fs = require('fs'),
  body_parser = require('body-parser'),
  app = express().use(body_parser.json()),
  https = require("https"),
  cors = require('cors'),
  jwt = require('jsonwebtoken'),
  dotenv = require('dotenv'),
  mongo = require('mongodb').MongoClient,
  util = require('util'),
  redis = require('redis');
 
dotenv.config();
const ObjectId = require('mongodb').ObjectID;
const MONGODB_URI = process.env.MONGODB_URI || 'mongodb://localhost:27017/';
const REDIS_HOST = process.env.REDIS_HOST || 'localhost';

const redisClient = redis.createClient({
	host: REDIS_HOST,
	port: 6379,
});

redisClient.on('error', (err) => console.log('Redis Client Error', err));

connectRedis();

const options = {
	key: fs.readFileSync("/etc/nginx/myuc2b.com.key"),
	cert: fs.readFileSync("/etc/nginx/myuc2b.com.crt")
};

/**
	Configs System
*/

let JWT_TOKEN = null;
let JWT_USER_ID = null;
let originsCors = null;
let publicUrl = null;
let appHash = null;
let WHATSAPP_APPS = null;

let MapView_GMapsAPIKey = null;
let MapView_GMapsUrl = null;

let multipleChannels = "false";

getConfigsApp('Configs').then((r) =>
{
	JWT_TOKEN = r.configs.AuthToken;
	JWT_USER_ID = r.configs.UserId;
	originsCors = r.configs.originsCors;
	publicUrl = r.configs.External_Url;
});

getConfigsApp('GoogleMaps').then((r) =>
{
	MapView_GMapsAPIKey = r.configs.MapView_GMapsAPIKey;
	MapView_GMapsUrl = r.configs.MapView_GMapsUrl;
});

findConfigsId('multiple_channels').then((r) =>
{
	console.log("multiple_channels..: " + r.valueSource);
	multipleChannels = r.valueSource;
});

// Suponha que getConfigsApp retorne uma Promise
async function setupWebhook() {
    try {
        const r = await getConfigsApp('whatsappMeta');
        console.log("getConfigsApp", JSON.stringify(r));
        logsMessages("WhatsApp Meta Post Webhook..: ", '/webhook' + r.appHash);

        WHATSAPP_APPS = r.configs;
        appHash = r.appHash;

        // Definindo o endpoint POST do webhook
        app.post('/webhook' + r.appHash, async (req, res) => {
            const { body } = req;

            if (body.object !== 'whatsapp_business_account') {
                logsMessages("whatsapp_business_account", "", true);
                return res.status(200).send('EVENT_RECEIVED');
            }

            // Processamento assíncrono das entradas
            for (const entry of body.entry) {
                //logsMessages("Sender entry", entry, true);
                console.log("messages..: ", JSON.stringify(body));
                for (const rChanges of entry.changes) {
                    const { field, value } = rChanges;

                    if (!field || field !== "messages" || !value) {
                        logsMessages(`not ${field ? 'messages' : 'changes.field'}`, "", true);
                        continue; // Usa 'continue' para pular para a próxima iteração
                    }

                    //logsMessages("Sender changes", value, true);

                    const isMultipleChannels = multipleChannels === "true" || multipleChannels === true;
                    const { metadata, messages } = value;
                    let apps;
                    let display_phone_number;
					
                    if (metadata) {
                        let appName = metadata.phone_number_id;
                        display_phone_number = metadata.display_phone_number;
                        apps = await configsApps(appName);
						
						if(apps && apps.PHONE_NUMBER && apps.PHONE_NUMBER == display_phone_number) {
							if(messages) {
								for (const message of messages) {
									const { profile, wa_id } = value.contacts[0];
									const phone_number_id = metadata ? metadata.phone_number_id : null;
									const sender_psid = isMultipleChannels && phone_number_id ? wa_id + phone_number_id : wa_id;

									handleMessageReceipt(sender_psid, profile, metadata, message);
								}
							}
						}
                    }
                }
            }

            logsMessages("EVENT_RECEIVED", "", false);
            res.status(200).send('EVENT_RECEIVED');
        });

        // Definindo o endpoint GET do webhook
        app.get('/webhook' + r.appHash, (req, res) => {
            logsMessages("WhatsApp Meta Get Webhook..: ", '/webhook' + r.appHash);
            logsMessages("whatsappMeta Webhook ", req.query, true);
            logsMessages("whatsappMeta Result  ", r, true);

            //const { 'hub.mode': mode, 'hub.verify_token': token, 'hub.challenge': challenge } = req.query;
			const { hub: { mode, verify_token: token, challenge } } = req.query;
			
            if (mode === 'subscribe' && token === r.VERIFY_TOKEN) {
                console.log('WEBHOOK_VERIFIED');
                res.status(200).send(challenge);
            } else {
                res.sendStatus(403);
            }
        });

    } catch (error) {
        console.error("Error setting up webhook: ", error);
        // Implemente o tratamento de erro conforme necessário
    }
}

setupWebhook();

https.createServer(options, app).listen(1338);

app.get("/system/reboot", verifyJWT, (req, res) =>
{
	console.log("/telegram/system/reboot");
    setTimeout(function ()
	{
        process.on("exit", function ()
		{
            require("child_process").spawn(process.argv.shift(), process.argv, {
                cwd: process.cwd(),
                detached : true,
                stdio: "inherit"
            });
        });
		
        process.exit(1);

    }, 1000);
	
	res.status(200).send({success:true});
})

app.get("/system/status", verifyJWT, (req, res) =>
{
	let enabled = false;
	
	console.log("WHATSAPP_APPS /system/status");
	
	let objectEntriesArray = Object.entries(WHATSAPP_APPS);
	
	let app = {};
	let apps = [];
	
	asyncForEach(objectEntriesArray, async (appName) =>
	{
		console.log("WHATSAPP_APPS appName " + appName);
		
		if(WHATSAPP_APPS[appName[0]].PAGE_ACCESS_TOKEN.length > 0)
		{
			enabled = true;
		}
	
		app = 	{
					metaId: WHATSAPP_APPS[appName[0]].PHONE_NUMBER_ID,
					wabaId: WHATSAPP_APPS[appName[0]].WABA_ID,
					number: WHATSAPP_APPS[appName[0]].PHONE_NUMBER,
					verify_token: WHATSAPP_APPS[appName[0]].VERIFY_TOKEN.substring(0, 10),
					token: WHATSAPP_APPS[appName[0]].PAGE_ACCESS_TOKEN.substring(0, 10),
					version: WHATSAPP_APPS[appName[0]].VERSION,
					enabled: enabled
				};
		
		apps.push(app);
	})
	.then(function (data)
	{
		let callbackUrl = `${publicUrl}:1338/webhook${appHash}`;
		
		let status = {
				running : true,
				enabled : enabled,
				callbackUrl : callbackUrl,
				apps 	: apps
			 }
		
		logsMessages("/system/status", status, true);
		
		res.status(200).send(status);
	});
})

app.post('/chat.postMessage', verifyJWT, (req, res) =>
{
	let body = req.body;
	
	logsMessages("/chat.postMessage", body, false);
	
	handleMessageSend(req.body);
	
	res.status(200).send('EVENT_SEND');
});

app.post('/chat.templateMessage', verifyJWT, (req, res) =>
{
	let body = req.body;
	
	console.log("***********************************************************");
	console.log("/chat.templateMessage: " + JSON.stringify(body));
	console.log("***********************************************************");
	
	handleTemplateMessageSend(req.body, req.body.template);
	
	res.status(200).send({"status":true});
});

function handleTemplateMessageSend(received_message) {
    logsMessages("handleTemplateMessageSend", received_message, true);
    
    let response;
    let sender_psid = received_message.fname;
    let inviteAgent = received_message.inviteAgent;
    
    if(received_message.appName) {
        let appName = received_message.appName;
        
        findTemplate(received_message.hsm_template_name).then((r) => {
            let t = '';
            let m = '';
            let type;
            let buttons;
            let parametersText = [];
            asyncForEach(r, async (c) => {
                m = c.default;
                t = c.template;
                type = c.type;
                buttons = c.buttons;
            })
            .then(function (data) {
                logsMessages("handleTemplateMessageSend template", m, true);
                
                let sendText = '';
                let p = 1;
                
                logsMessages("handleTemplateMessageSend text", t, true);
                
                asyncForEach(received_message.parameters, async (text) => {
                    logsMessages("handleTemplateMessageSend parameters", text, true);
                    
                    if (text && text.length > 0) {
                        t = t.replace('{{'+p.toString()+'}}', text);
                    
                        parametersText.push({
                            type: "text",
                            text: text
                        });
                        
                        p = p + 1;
                    }
                })
                .then(function (data) {
                    logsMessages("handleTemplateMessageSend sender_psid", sender_psid, true);
                    console.log("buttons ", JSON.stringify(buttons));
                    if(buttons && buttons[0] && buttons[0].type === "FLOW") {
                        let buttons = {
                            type: "button",
                            sub_type: "flow",
                            index: 0
                        };
                        
						if(parametersText.length > 0) {
							m.template.components[1].parameters = parametersText;
						}
						
                        m.template.components.push(buttons);
                    }
                    
                    m.to = sender_psid;
                    
                    if(type == "image" || type == "video" || type == "document") {
                        m.template.components[1].parameters = parametersText;
                    } else {
                        if(parametersText.length > 0) {
                            m.template.components[1].parameters = parametersText;
                        }
                    }
                    
                    logsMessages("handleTemplateMessageSend parametersText", parametersText, true);
                    logsMessages("handleTemplateMessageSend t..: ", t, false);
                    logsMessages("handleTemplateMessageSend m..: ", m, false);
                    logsMessages("handleTemplateMessageSend textOrig..: ", received_message.textOrig, false);
                    configsApps(appName).then((config) => {
                        logsMessages("handleMessageSend config", config, true);
                        callMessageSendAPI(sender_psid, "", m, config, true, inviteAgent, received_message);
                    });
                });
            });
        });
    }
}

async function findTemplate(name)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r = null;
	
	if(!client)
	{
		return;
	}
	
	try
	{
		const db = client.db('mytuite');
		r = await db.collection('mytuite_templates').find({ name : name }).toArray();
	}
	catch(err)
	{
		await client.close();
		return;
	}
	finally
	{
		await client.close();
		return r;
	}
}

async function handleMessageReceipt(sender_psid, profile, metadata, received_message)
{
	logsMessages("/handleMessageReceipt message", received_message, true);
	logsMessages("/handleMessageReceipt profile", profile, true);
	
	let response;
	let sender_fname = received_message.from;
	
	if (received_message.type)
	{
		let tmid = "";
		if(received_message.context && received_message.context.id) {
			let bmid = received_message.context.id;
			console.log("handleMessageReceipt bmid " + bmid);
			tmid = await findReplyToMessageBmid(bmid)
		}
		
		let messageId;
		if (received_message.id) {
			messageId = received_message.id;
		}
		
		console.log("handleMessageReceipt tmid " + tmid);
		console.log("handleMessageReceipt messageId " + messageId);
		
		if(received_message.type == "text")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			response =
			{
				_id : sender_psid,
				tmid : tmid,
				messageId : messageId,
				name : sender_psid,
				fname : sender_fname,
				channel : "WhatsApp",
				broker : "Meta",
				profile_name : profile_name,
				appName : metadata.phone_number_id,
				type : "text",
				text : received_message.text.body.replace(/"/g,'`')
			}
			
			logsMessages("callCoreSendAPI", response, true);
			
			callCoreSendAPI(response);
		}
		else if(received_message.type == "order") {
			let profile_name = "";
			let contentForm = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			let products = [];
			if(received_message.order && received_message.order.product_items) {
				products = received_message.order.product_items;
			}
			
			products.forEach(item => {
				contentForm = contentForm.concat(`Produto Varejista ID: ${item.product_retailer_id} \n`);
				contentForm = contentForm.concat(`Quantidade: ${item.quantity} \n`);
				contentForm = contentForm.concat(`Preço: ${item.item_price} \n`);
				contentForm = contentForm.concat(`Moeda: ${item.currency} \n`);
			});
			
			const price_products_purchased = products.reduce((sum, product) => {
				return sum + (product.quantity * product.item_price);
			}, 0);

			console.log("Total:", price_products_purchased.toFixed(2));
			
			setPaymentCatalog(sender_psid, price_products_purchased, "");
			
			response =
			{
				_id : sender_psid,
				name : sender_psid,
				fname : sender_fname,
				channel : "WhatsApp",
				broker : "Meta",
				profile_name : profile_name,
				appName : metadata.phone_number_id,
				type : "text",
				text : contentForm.replace(/"/g,'`')
			}
			
			logsMessages("callCoreSendAPI", response, true);
			
			callCoreSendAPI(response);
			
		}
		else if(received_message.type == "interactive")
		{
			logsMessages("callCoreSendAPI interactive ", received_message, true);
			logsMessages("callCoreSendAPI interactive ", received_message, true);
			logsMessages("callCoreSendAPI interactive ", received_message, true);
			logsMessages("callCoreSendAPI interactive ", received_message, true);
			
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.interactive && received_message.interactive.button_reply && received_message.interactive.button_reply.id)
			{
				response =
				{
					_id : sender_psid,
					name : sender_psid,
					fname : sender_fname,
					channel : "WhatsApp",
					broker : "Meta",
					profile_name : profile_name,
					appName : metadata.phone_number_id,
					type : "text",
					text : received_message.interactive.button_reply.id.replace(/"/g,'`')
				}
				
				logsMessages("callCoreSendAPI", response, true);
				
				callCoreSendAPI(response);
			}
			else if(received_message.interactive && received_message.interactive.list_reply && received_message.interactive.list_reply.id)
			{
				response =
				{
					_id : sender_psid,
					name : sender_psid,
					fname : sender_fname,
					channel : "WhatsApp",
					broker : "Meta",
					profile_name : profile_name,
					appName : metadata.phone_number_id,
					type : "text",
					text : received_message.interactive.list_reply.id.replace(/"/g,'`')
				}
				
				logsMessages("callCoreSendAPI", response, true);
				
				callCoreSendAPI(response);
			}
			else if(received_message.interactive && received_message.interactive.nfm_reply && received_message.interactive.nfm_reply.response_json)
			{
				let responseString = received_message.interactive.nfm_reply.response_json;
				let parsedResponse = JSON.parse(responseString);
				
				let contentForm = "";
				let contentArray = {};
				for (let key in parsedResponse) {
					if (parsedResponse.hasOwnProperty(key)) {					
						if(key == "screen_0_TextInput_0") {
							contentForm = contentForm.concat(`Nome: ${parsedResponse[key]} \n`);
							contentArray.name = parsedResponse[key];
						} else if(key == "screen_0_TextInput_1") {
							contentForm = contentForm.concat(`Telefone: ${parsedResponse[key]} \n`);
							contentArray.phone = parsedResponse[key];
						} else if(key == "screen_0_TextInput_2") {
							contentForm = contentForm.concat(`E-mail: ${parsedResponse[key]} \n`);
							contentArray.email = parsedResponse[key];
						} else if(key == "screen_0_TextInput_3") {
							contentForm = contentForm.concat(`Empresa: ${parsedResponse[key]} \n`);
							contentArray.company = parsedResponse[key];
						}
						console.log(`Key: ${key}, Value: ${parsedResponse[key]}`);
					}
				}
				
				findCrmByContactId(sender_psid, metadata, contentArray);
				
				console.log("contentArray ", JSON.stringify(contentArray));
				console.log("received_message.interactive contentForm ".concat(contentForm));
				
				response =
				{
					_id : sender_psid,
					name : sender_psid,
					fname : sender_fname,
					channel : "WhatsApp",
					broker : "Meta",
					profile_name : profile_name,
					appName : metadata.phone_number_id,
					type : "text",
					text : contentForm.replace(/"/g,'`')
				}
				
				logsMessages("callCoreSendAPI", response, true);
				
				callCoreSendAPI(response);
			}
		}
		else if(received_message.type == "button")
		{
			logsMessages("callCoreSendAPI button ", received_message, true);
			logsMessages("callCoreSendAPI button ", received_message, true);
			logsMessages("callCoreSendAPI button ", received_message, true);
			logsMessages("callCoreSendAPI button ", received_message, true);
			
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.button && received_message.button.text)
			{
				response =
				{
					_id : sender_psid,
					name : sender_psid,
					fname : sender_fname,
					channel : "WhatsApp",
					broker : "Meta",
					profile_name : profile_name,
					appName : metadata.phone_number_id,
					type : "text",
					text : received_message.button.text.replace(/"/g,'`')
				}
				
				logsMessages("callCoreSendAPI", response, true);
				
				callCoreSendAPI(response);
			}
		}
		else if(received_message.type == "contacts")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.contacts) {
				
				let contacts = received_message.contacts;

				asyncForEach(contacts, async (contact) =>
				{
					let contact_first_name = contact.name.first_name;
					
					if(contact.phones) {
						asyncForEach(contact.phones, async (phone) =>
						{
							let text_contact_type = phone.type;
							let text_contact_wa_id = phone.wa_id;
							
							let text_contact = contact_first_name.concat("\n ").concat(text_contact_type).concat(": ").concat(text_contact_wa_id);
							
							response =
							{
								_id : sender_psid,
								name : sender_psid,
								fname : sender_fname,
								channel : "WhatsApp",
								broker : "Meta",
								profile_name : profile_name,
								appName : metadata.phone_number_id,
								type : "text",
								text : text_contact.replace(/"/g,'`')
							}
							
							logsMessages("callCoreSendAPI", response, true);
			
							callCoreSendAPI(response);
						});
					}
					else {
						console.log("contact phones is null");
					}
				});
			}
			else {
				console.log("contact is null");
			}
		}
		else if(received_message.type == "location")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			MapView_GMapsUrl = MapView_GMapsUrl.replace(/@latitude/g, received_message.location.latitude);
			MapView_GMapsUrl = MapView_GMapsUrl.replace(/@longitude/g, received_message.location.longitude);

			response =
			{
				_id : sender_psid,
				name : sender_psid,
				fname : sender_fname,
				channel : "WhatsApp",
				broker : "Meta",
				profile_name : profile_name,
				appName : received_message.app,
				type : "location",
				url : MapView_GMapsUrl,
				latitude : received_message.location.latitude,
				longitude : received_message.location.longitude
			}
			
			logsMessages("callCoreSendAPI", response, true);
			
			callCoreSendAPI(response);
		}
		else if(received_message.type == "document")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.document)
			{
				if(received_message.document.id)
				{
					mediaIdUrl(received_message.document.id, metadata).then((r) =>
					{
						logsMessages("handleMessageReceipt mediaIdUrl", r, true);
						
						let filename;
						
						if(!received_message.document.filename)
						{
							if(received_message.document.mime_type == "audio/wav")
							{
								filename = "audio_" + Date.now() + ".wav";
							}
							else if(received_message.document.mime_type == "audio/aac")
							{
								filename = "audio_" + Date.now() + ".aac";
							}
							else if(received_message.document.mime_type == "audio/mp3")
							{
								filename = "audio_" + Date.now() + ".mp3";
							}
							else if(received_message.document.mime_type == "audio/mp4")
							{
								filename = "audio_" + Date.now() + ".mp4";
							}
							else if(received_message.document.mime_type == "audio/mpeg")
							{
								filename = "audio_" + Date.now() + ".mpeg";
							}
							else if(received_message.document.mime_type == "audio/amr")
							{
								filename = "audio_" + Date.now() + ".amr";
							}
							else if(received_message.document.mime_type == "audio/ogg")
							{
								filename = "audio_" + Date.now() + ".ogg";
							}
							else if(received_message.document.mime_type == "text/plain")
							{
								filename = "document_" + Date.now() + ".txt";
							}
							else if(received_message.document.mime_type == "application/pdf")
							{
								filename = "document_" + Date.now() + ".pdf";
							}
							else if(received_message.document.mime_type == "application/vnd.ms-powerpoint")
							{
								filename = "document_" + Date.now() + ".ptt";
							}
							else if(received_message.document.mime_type == "application/msword")
							{
								filename = "document_" + Date.now() + ".doc";
							}
							else if(received_message.document.mime_type == "application/vnd.ms-excel")
							{
								filename = "document_" + Date.now() + ".xls";
							}
							else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document")
							{
								filename = "document_" + Date.now() + ".docx";
							}
							else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.presentationml.presentation")
							{
								filename = "document_" + Date.now() + ".pptx";
							}
							else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
							{
								filename = "document_" + Date.now() + ".xlsx";
							}
							else if(received_message.document.mime_type == "application/json")
							{
								filename = "document_" + Date.now() + ".json";
							}
							else if(received_message.document.mime_type == "image/jpeg")
							{
								filename = "image_" + Date.now() + ".jpg";
							}
							else if(received_message.document.mime_type == "image/png")
							{
								filename = "image_" + Date.now() + ".png";
							}
						}
						else
						{
							filename = received_message.document.filename;
						}
						
						if(r.url)
						{
							let appName = metadata.phone_number_id;
							
							configsApps(appName).then((config) =>
							{
								response =
								{
									_id : sender_psid,
									name : sender_psid,
									fname : sender_fname,
									channel : "WhatsApp",
									broker : "Meta",
									profile_name : profile_name,
									appName : metadata.phone_number_id,
									token : config.PAGE_ACCESS_TOKEN,
									messageId : received_message.document.id,
									caption : received_message.document.caption,
								}
							
								if(received_message.document.mime_type == "audio/wav")
								{
									response.type = 'audio/wav';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/x-wav")
								{
									response.type = 'audio/x-wav';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/mp3")
								{
									response.type = 'audio/mp3';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/aac")
								{
									response.type = 'audio/aac';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/mp4")
								{
									response.type = 'audio/mp4';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/mpeg")
								{
									response.type = 'audio/mpeg';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "video/mpeg")
								{
									response.type = 'video/mpeg';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/amr")
								{
									response.type = 'audio/amr';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "audio/ogg")
								{
									response.type = 'audio/ogg';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "text/plain")
								{
									response.type = 'text/plain';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/pdf")
								{
									response.type = 'application/pdf';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/vnd.ms-powerpoint")
								{
									response.type = 'application/vnd.ms-powerpoint';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/msword")
								{
									response.type = 'application/msword';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/vnd.ms-excel")
								{
									response.type = 'application/vnd.ms-excel';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document")
								{
									response.type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.presentationml.presentation")
								{
									response.type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.presentationml.presentation")
								{
									response.type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.document.mime_type == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
								{
									response.type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else
								{
									response.type = 'file';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
							});
						}
					});
				}
			}
		}
		else if(received_message.type == "image")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.image)
			{
				if(received_message.image.id)
				{
					mediaIdUrl(received_message.image.id, metadata).then((r) =>
					{
						logsMessages("handleMessageReceipt mediaIdUrl", r, true);
						
						if(r.url)
						{
							let appName = metadata.phone_number_id;
							
							configsApps(appName).then((config) =>
							{
								response =
								{
									_id : sender_psid,
									name : sender_psid,
									fname : sender_fname,
									channel : "WhatsApp",
									broker : "Meta",
									profile_name : profile_name,
									appName : metadata.phone_number_id,
									token : config.PAGE_ACCESS_TOKEN,
									messageId : r.id
								};
								
								if(received_message.image.mime_type == "image/jpeg")
								{
									response.type = received_message.image.mime_type;
									response.title = "image_" + received_message.image.id + ".jpg";
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.image.mime_type == "image/png")
								{
									response.type = received_message.image.mime_type;
									response.title = "image_" + received_message.image.id + ".png";
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else {
									response.type = 'image';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
							});
						}
					});
				}
			}
		}
		else if(received_message.type == "video")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.video)
			{
				if(received_message.video.id)
				{
					mediaIdUrl(received_message.video.id, metadata).then((r) =>
					{
						logsMessages("handleMessageReceipt mediaIdUrl", r, true);
						
						if(r.url)
						{
							let appName = metadata.phone_number_id;
							
							configsApps(appName).then((config) =>
							{
								response =
								{
									_id : sender_psid,
									name : sender_psid,
									fname : sender_fname,
									channel : "WhatsApp",
									broker : "Meta",
									profile_name : profile_name,
									appName : metadata.phone_number_id,
									token : config.PAGE_ACCESS_TOKEN,
									messageId : r.id
								};
								
								if(received_message.video.mime_type == "video/mp4")
								{
									response.type = "video/mp4";
									response.title = "video_"+received_message.video.id + ".mp4";
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.video.mime_type == "video/3gp")
								{
									response.type = "video/3gp";
									response.title = "video_"+received_message.video.id + ".3gp";
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else
								{
									response.type = 'video';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
							});
						}
					});
				}
			}
		}
		else if(received_message.type == "audio")
		{
			let profile_name = "";
			
			if(profile.name)
			{
				profile_name = profile.name;
			}
			
			if(received_message.audio)
			{
				if(received_message.audio.id)
				{
					mediaIdUrl(received_message.audio.id, metadata).then((r) =>
					{
						logsMessages("handleMessageReceipt mediaIdUrl", r, true);
						
						if(r.url)
						{
							let appName = metadata.phone_number_id;
							
							configsApps(appName).then((config) =>
							{
								response =
										{
											_id : sender_psid,
											name : sender_psid,
											fname : sender_fname,
											channel : "WhatsApp",
											broker : "Meta",
											profile_name : profile_name,
											appName : metadata.phone_number_id,
											token : config.PAGE_ACCESS_TOKEN,
											messageId : r.id
										}
												
								if(received_message.audio.mime_type == "audio/ogg; codecs=opus")
								{
									response.type = "audio/ogg";
									response.title = "audio_"+received_message.audio.id + ".opus";
									response.file_size = r.file_size;
									response.url = r.url;
										
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.audio.mime_type == "audio/aac")
								{
									response.type = "audio/aac";
									response.title = "audio_"+received_message.audio.id + ".aac";
									response.file_size = r.file_size;
									response.url = r.url;
										
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.audio.mime_type == "audio/mp4")
								{
									response.type = "audio/mp4";
									response.title = "audio_"+received_message.audio.id + ".mp4";
									response.file_size = r.file_size;
									response.url = r.url;
										
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.audio.mime_type == "audio/mpeg")
								{
									response.type = "audio/mpeg";
									response.title = "audio_"+received_message.audio.id + ".mpeg";
									response.file_size = r.file_size;
									response.url = r.url;
										
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.audio.mime_type == "audio/amr")
								{
									response.type = "audio/amr";
									response.title = "audio_"+received_message.audio.id + ".amr";
									response.file_size = r.file_size;
									response.url = r.url;
										
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else if(received_message.audio.mime_type == "audio/ogg")
								{
									response.type = "audio/ogg";
									response.title = "audio_"+received_message.audio.id + ".ogg";
									response.file_size = r.file_size;
									response.url = r.url;
										
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
								else
								{
									response.type = 'audio';
									response.title = filename;
									response.file_size = r.file_size;
									response.url = r.url;
									
									logsMessages("callCoreSendAPI", response, true);
							
									callCoreSendAPI(response);
								}
							});
						}
					});
				}
			}
		}
	}
}

async function handleMessageSend(received_message)
{
	logsMessages("handleMessageSend", received_message, true);
	
	let response;
	let sender_psid = received_message._id;
	let messageId = "";
	let tmid = "";
	let bmid = "";
	if(received_message.messageId) {
		messageId = received_message.messageId;
	}
	
	if(received_message.tmid && received_message.tmid.length > 0) {
		tmid = received_message.tmid;
		bmid = await findReplyToMessageTmid(tmid)
	}
	
	console.log("handleMessageSend messageId: " + messageId);
	console.log("handleMessageSend tmid: " + tmid);
	console.log("handleMessageSend bmid: " + bmid);
	
	if(received_message.appName)
	{
		let appName = received_message.appName;
		
		configsApps(appName).then((config) =>
		{
			if(multipleChannels == "true" || multipleChannels == true)
			{
				sender_psid = sender_psid.replace(config.PHONE_NUMBER_ID, '');
			}
			
			//logsMessages("handleMessageSend config", config, true);
			
			if (received_message.type == "text")
			{
				response =
				{
					"messaging_product": "whatsapp",
					"preview_url": false,
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "text",
					"text": {
						"body": received_message.text
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if (received_message.type == "button")
			{
				var keyboard = [];
				logsMessages("Meta sendButton Button", received_message.text[0].actions, true);
				let textActionsButton = received_message.text[0].actions;
				console.log("textActionsButton..: ".concat(textActionsButton.length));
				
				if(textActionsButton.length > 3) {
					asyncForEach(textActionsButton, async (c) => {
						keyboard.push( { "id": c.msg, "title": c.text, "description" : c.description });
					})
					.then(function (data)
					{
						let title = "Menu";
						if(received_message.text[0].title) {
							title = received_message.text[0].title;
						}
						
						response =
						{
							"messaging_product": "whatsapp",
							"recipient_type": "individual",
							"to": sender_psid,
							"type": "interactive",
							"interactive": {
								"type": "list",
								"body": {
									"text": received_message.text[0].text
								},
								"action": {
									"button": title,
									"sections": [{
										"title": "",
										"rows": keyboard,
									}]
								}
							}
						};
						
						logsMessages("Meta sendMessage button", response, false);
					
						callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
					});
				}
				else {
					logsMessages("Meta sendButton Button", received_message.text[0].actions, true);
					let textActionsButton = received_message.text[0].actions;
					
					asyncForEach(textActionsButton, async (c) =>
					{
						keyboard.push({ "type": "reply", "reply": { "id": c.msg, "title": c.text } });
						logsMessages("Meta sendButton Button", keyboard, true);
					})
					.then(function (data)
					{
						response =
						{
							"messaging_product": "whatsapp",
							"recipient_type": "individual",
							"to": sender_psid,
							"type": "interactive",
							"interactive": {
								"type": "button",
								"body": {
									"text": received_message.text[0].text
								},
								"action": {
									"buttons": JSON.stringify(keyboard)
								}
							}
						};
						
						logsMessages("Meta sendMessage button", response, false);
					
						callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
					});
				}
			}
			else if (received_message.type == "image")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "image",
					"image":
					{
						"link": attachment_url
					}
				};

				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if (received_message.type == "location")
			{
				let coordinates = received_message.coordinates;
				
				if(received_message.coordinates && coordinates[0] && coordinates[1])
				{
					response =
					{
						"messaging_product": "whatsapp",
						"recipient_type": "individual",
						"to": sender_psid,
						"type": "location",
						"location":
						{
							"longitude": coordinates[0],
							"latitude": coordinates[1],
							"name": "",
							"address": ""
						}
					};
					
					callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
				}
			}
			else if (received_message.type == "file")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "document",
					"document":
					{
						"link": attachment_url,
						"caption": attachment_title
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if (received_message.type == "video")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "video",
					"video": {
						"link": attachment_url,
						"caption": attachment_title
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if (received_message.type == "audio")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "audio",
					"audio": {
						"link": attachment_url
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if (received_message.type == "text/plain")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "document",
					"document": {
						"link": attachment_url,
						"caption": attachment_title
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if (received_message.type == "csv")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "document",
					"document": {
						"link": attachment_url,
						"caption": attachment_title
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
			else if(received_message.type == "csv" || received_message.type == "xlsx" || received_message.type == "docx" || received_message.type == "pptx")
			{
				let attachment_title = received_message.title;
				let attachment_url = received_message.url;
				
				response =
				{
					"messaging_product": "whatsapp",
					"recipient_type": "individual",
					"to": sender_psid,
					"type": "document",
					"document": {
						"link": attachment_url,
						"caption": attachment_title
					}
				};
				
				callMessageSendAPI(sender_psid, messageId, response, config, false, "", {});
			}
		});
	}
}

async function mediaIdUrl(mediaId, metadata)
{
	return new Promise(function(resolve, reject) {
		logsMessages("mediaIdUrl mediaId ", mediaId, true);
		
		let appName = metadata.phone_number_id;
		
		logsMessages("mediaIdUrl appName ", appName, false);
		
		configsApps(appName).then((config) => {
			logsMessages("mediaIdUrl config ", config, true);
		
			var options = {
					'method': 'GET',
					'url': 'https://graph.facebook.com/'+config.VERSION+'/'+mediaId,
					'headers': {
						'Content-Type': 'application/json',
						'Authorization': 'Bearer ' + config.PAGE_ACCESS_TOKEN
					}
				};
			
		
			request(options, function(err, resp, body) {
				let media = JSON.parse(body);
				
				logsMessages("mediaIdUrl body ", media, true);
				logsMessages("mediaIdUrl url ", media.url, true);
				
				if (err) {
					let r = { url: "" };
					logsMessages("mediaIdUrl r err ", r, true);
					resolve(r);
				}
				else {
					if(media.url) {
						let r = { url: media.url }
						logsMessages("mediaIdUrl r valido ", r, true);
						resolve(r);
					}
					else {
						let r = { url: "" }
						logsMessages("mediaIdUrl r vazio ", r, true);
						resolve(r);
					}
				}
			})
		})
    })
}

function callCoreSendAPI(response)
{
	logsMessages("callCoreSendAPI", response, true);
	
	var options =
	{
		'method': 'POST',
		'url': 'http://localhost:3034/webhook',
		'headers':
		{
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(response)
	};
	
	request(options, function(err, resp, body) {
		
	})
}

function callMessageSendAPI(sender_psid, messageId, postData, config, isHSM, userId, received_message)
{
	logsMessages("callMessageSendAPI postData ", postData, true);
	
	if (messageId && messageId.length > 0) {
		postData.context = {
			"message_id": messageId
		};
	}
	
	console.log("callMessageSendAPI postData ", JSON.stringify(postData));

	var options =
		{
			'method': 'POST',
			'url': 'https://graph.facebook.com/'+config.VERSION+'/'+config.PHONE_NUMBER_ID+'/messages',
			'headers': {
				'Content-Type': 'application/json',
				'Authorization': 'Bearer ' + config.PAGE_ACCESS_TOKEN
			},
			body: JSON.stringify(postData)
		};
		
		console.log("callMessageSendAPI ", JSON.stringify(options));
		//logsMessages("callMessageSendAPI options ", options, true);
		
		request(options, function (error, response)
		{
			console.log("callMessageSendAPI response ", response.body);
			let payload = JSON.parse(response.body);
			if (payload && payload.messages && payload.messages.length > 0) {
				if(payload.messages[0].id) {
					setReplyToMessageBmid(payload.messages[0].id, messageId);
				}
			}
			
			if (error) {
				logsMessages("callMessageSendAPI error ", error, true);
			}
			else {				
				if(isHSM == true) {
					let postBuffer = response;
					setWhatsAppTemplateHistory(postData, postBuffer, userId, received_message, config);
				}
			}
		});
}

async function getConfigsApp(_appName) {
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r = null;
	let configs = [];

	if(!client) {
		return;
	}

	try {
		const db = client.db('mytuite');
		r = await db.collection('mytuite_apps').find({ appName: _appName }).limit(1).toArray();
		
		asyncForEach(r, async (c) => {
			configs = c;
		});
	}
	catch(err) {
		await client.close();
		return;
	}
	finally {
		await client.close();
		return configs;
	}
}

function profileData(profileId)
{
	var options =
		{
			uri: `https://graph.facebook.com/${profileId}?`,
			qs: {
				access_token: TOKEN, 
				fields:  'first_name,last_name,profile_pic'
			},
			method: 'GET'
		};	

		return new Promise(function(resolve, reject)
		{
			request(options, function(err, resp, body)
			{
				console.log("profileData err ", JSON.stringify(err));
				console.log("profileData body ", body);
				
				let r =
						{
							fname: ""
						};
						
				if (err)
				{
					resolve(r);
				}
				else
				{
					if(body.success == true)
					{
						r =
						{
							fname
						};
						
						resolve(r);
					}
					else
					{
						resolve(r);
					}
				}
			})
		})
}

async function findConfigsId(_id)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r = null;
	let configs = null;
	
	if(!client)
	{
		return;
	}
	
	try
	{
		const db = client.db('mytuite');
		r = await db.collection('mytuite_configs').find({_id:_id}).toArray();
		
		asyncForEach(r, async (c) =>
		{
			configs = c;
		});
	}
	catch(err)
	{
		await client.close();
		return;
	}
	finally
	{
		await client.close();
		return configs;
	}
}

async function setWhatsAppTemplateHistory(postData, postBuffer, userId, received_message, config)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r = null;
	
	if(!client)
	{
		return;
	}
	
	let response = JSON.parse(postBuffer.body);
	
	let status = "";
	if(postBuffer.statusCode && postBuffer.statusCode == 200){
		status = "submitted"
	}
	
	try
	{
		let query =
		{
			_id: ObjectId(),
			channel: response.messaging_product,
			source: config.PHONE_NUMBER,
			destination: postData.to,
			appName: received_message.appName,
			message: received_message.text,
			type: received_message.type,
			linked: received_message.linkedToFlow,
			status: status,
			whoSent: received_message.whoSent,
			userId: userId,
			messageId: response.messages[0].id,
			_sendAt: new Date()
		};
		
		const db = client.db('mytuite');
		r = await db.collection("mytuite_templates_history").insertOne(query);
	}
	catch(err)
	{
		await client.close();
		return;
	}
	finally
	{
		await client.close();
		return;
	}
}

/** Crm */

async function findCrmByContactId(roomId, metadata, contentArray) {
    try {
        const { phone: roomNumber, name: roomName, company: roomCompany, email: roomEmail } = contentArray;
        const protocolId = await newProtocol();

        console.log("findCrmByContactId roomNumber:", roomNumber);
        console.log("findCrmByContactId roomName:", roomName);
        console.log("findCrmByContactId roomCompany:", roomCompany);
        console.log("findCrmByContactId roomEmail:", roomEmail);
        console.log("findCrmByContactId protocolId:", protocolId);

		const account = await setAccountByRoom(roomNumber, roomName, roomCompany);
		console.log("findCrmByContactId account:", JSON.stringify(account));

		const contact = await setContactByRoom(roomId, roomName, account, protocolId, contentArray);
		console.log("findCrmByContactId contact:", JSON.stringify(contact));

		const uRoom = await setContactIdByRoom(roomId, contact, account);
		console.log("findCrmByContactId uRoom:", JSON.stringify(uRoom));

		const payload = createPayload(contact._id, roomName);
		const activity = await insertActivity(payload, protocolId);
		console.log("findCrmByContactId contactId ok");
		return activity;
        
    } catch (error) {
        console.error("Erro em findCrmByContactId:", error);
        throw error;
    }
}

function createPayload(contactId, roomName) {
    const currentDate = new Date();
    const date = currentDate.toISOString().split('T')[0];
    const time = currentDate.toTimeString().split(' ')[0];

    return {
        contactId: contactId,
        name: roomName,
        data: date,
        time: time,
        public: true,
        status: true,
        type: "ChatBot",
        owner: {
            _id: null,
            name: null
        },
        comments: "ChatBot"
    };
}

async function setPaymentCatalog(roomId, amount, description) {
    try {
        let valuesPaymentAmount = 0;
        let hashValuesPayment = await redisClient.hGetAll(roomId);

        if (util.isObject(hashValuesPayment) && hashValuesPayment.payment_amount) {
            valuesPaymentAmount = parseFloat(hashValuesPayment.payment_amount);
        }

        valuesPaymentAmount += parseFloat(amount);

        await redisClient.hSet(roomId, 'payment_amount', valuesPaymentAmount.toString());
        await redisClient.hSet(roomId, 'payment_description', description);

        console.log(`valuesPaymentAmount: ${valuesPaymentAmount}`);
        console.log(`Key: amount, Value: ${valuesPaymentAmount}, hSet Ok`);
        console.log(`Key: description, Value: ${description}, hSet Ok`);
		const hashValues = await redisClient.hGetAll(roomId);
		console.log("flowClient hashValues " + JSON.stringify(hashValues));
        console.log('Conectado ao Redis com sucesso!');
    } catch (error) {
        console.error('Falha ao conectar ao Redis:', error);
    } finally {
        await redisClient.quit();
    }
}

async function setAccountByRoom(roomNumber, roomName, roomCompany)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r =
		{
			status: false
		}
	
	if(!client)
	{
		return r;
	}
	
	let createdBy = new Date();
	let query;
	let update;
	let _id = ObjectId();
	
	query =
			{
				_id : _id
			};
	
	update = { $set: { name: roomName, company: roomCompany, createdBy : createdBy, phone : roomNumber } };
	
	console.log("setAccountByRoom Query..: ", JSON.stringify(query));
	console.log("setAccountByRoom Update..: ", JSON.stringify(update));
	
	let options = { "upsert": true };

	try
	{
		const db = client.db('myleads');
		await db.collection('account').updateOne(query, update, options);
	}
	catch(err)
	{
		await client.close();
		return r;
	}
	finally
	{
		await client.close();

		r =
		{
			_id: _id,
			status: true
		}
		
		return r;
	}
}

async function setContactByRoom(roomId, roomName, account, protocolId, contentArray)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r =
		{
			status: false
		}
	
	if(!client)
	{
		return r;
	}
	
	let createdBy = new Date();
	let accountId = account._id;
	let query;
	let update;
	let _id = ObjectId();
	
	query =
			{
				roomId : roomId
			};
	
	update = { $set: { roomId : roomId, name : roomName, accountId : accountId, email : contentArray.email, company : contentArray.company, phone : contentArray.phone, createdBy : createdBy } };
		
	console.log("setContactByRoom Query..: ", JSON.stringify(query));
	console.log("setContactByRoom Update..: ", JSON.stringify(update));
	
	let options = { "upsert": true };

	try
	{
		const db = client.db('myleads');
		await db.collection('contact').updateOne(query, update, options);
	}
	catch(err)
	{
		await client.close();
		return r;
	}
	finally
	{
		await client.close();
		
		r =
		{
			_id: _id,
			status: true
		}
		
		return r;
	}
}

async function setContactIdByRoom(roomId, contact, account)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	
	let r =
		{
			status: false
		}
		
	if(!client)
	{
		return r;
	}
	
	let contactId = contact._id;
	let accountId = account._id;
	
	let query =
			{
				_id : roomId
			};
	
	let update = { $set: { 'info.contactId': contactId, 'info.accountId': accountId } };
	let options = { "upsert": true };
	
	console.log("setContactIdByRoom Query..: ", JSON.stringify(query));
	console.log("setContactIdByRoom Update..: ", JSON.stringify(update));

	try
	{
		const db = client.db('rocketchat');
		await db.collection('rocketchat_room').updateOne(query, update, options);
	}
	catch(err)
	{
		await client.close();
		return r;
	}
	finally
	{
		await client.close();
		
		r =
		{
			status: true
		}
		
		return r;
	}
}

async function insertActivity(body, protocolId) {
    console.log("insertActivity..: ", JSON.stringify(body));

	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	
	if(!client)
	{
		return r;
	}
	
    try {
        const db = client.db('myleads');
        
        let data = body.data;
        let time = body.time;
        let scheduleString = `${data}T${time}`;
        let schedule = new Date(scheduleString);
        
        let query = {
            _id: new ObjectId(),
            protocol: protocolId,
            name: body.name,
            contactId: new ObjectId(body.contactId),
            type: body.type,
            comments: body.comments,
            schedule: new Date(schedule),
            createdBy: new Date(),
            _insertAt: new Date(),
            public: body.public
        };

        console.log("insertActivity Query..: ", JSON.stringify(query));
        
        const result = await db.collection("activity").insertOne(query);
        
        return {
            _id: query._id,
            protocol: protocolId,
            status: true
        };
    } catch (err) {
        console.error("Erro ao inserir atividade:", err);
        return { status: false };
    } finally {
        await client.close();
    }
}

async function findOneByRoomId(roomId)
{
	const client = await mongo.connect(MONGODB_URI, { useNewUrlParser: true, useUnifiedTopology: true }).catch(err => { console.log(err); });
	let r = null;
	let configs = null;
	
	if(!client)
	{
		return;
	}
	
	try
	{
		const db = client.db('rocketchat');
		r = await db.collection('rocketchat_room').find({ _id : roomId }).limit(1).toArray();
		
		asyncForEach(r, async (c) =>
		{
			configs = c;
		});
	}
	catch(err)
	{
		await client.close();
		return;
	}
	finally
	{
		//console.log("chatbot Room Id ", JSON.stringify(configs));
		await client.close();
		return configs;
	}
}

async function newProtocol()
{
	return new Promise((resolve, reject) =>
	{
		let data = new Date();
		let newProtocol = ("0" + data.getDate()).substr(-2)+("0" + (data.getMonth() + 1)).substr(-2)+data.getFullYear()+data.getHours()+data.getMinutes()+data.getSeconds()+Math.floor(10 + Math.random() * 99);
		resolve(newProtocol);
	});
}

async function connectRedis() {
    try {
        await redisClient.connect();
        console.log('Conectado ao Redis com sucesso!');
    } catch (error) {
        console.error('Falha ao conectar ao Redis:', error);
    }
}

async function setReplyToMessageBmid(bmid, tmid)
{
	try {
		let dataSet = { tmid : tmid };
		console.log("setReplyToMessageBmid bmid ".concat(bmid));
		console.log("setReplyToMessageBmid tmid ".concat(tmid));
		
		for (const [key, value] of Object.entries(dataSet)) {
            const resultSet = await redisClient.hSet(bmid, key, value);
            console.log("Key: ".concat(key).concat(", Value: ").concat(value).concat("hSet Ok: ").concat(resultSet));
        }
	}
	catch(err) {
		return;
	}
	finally {
		const resultGet = await redisClient.hGetAll(bmid);
        console.log("Result hGetAll: ", resultGet);
		return;
	}
}

async function findReplyToMessageBmid(bmid)
{
	let tmid = "";
	try {
		const resultGet = await redisClient.hGetAll(bmid);
        console.log("findReplyToMessageBmid bmid: ", bmid);
        console.log("findReplyToMessageBmid hGetAll: ", resultGet);
		if(resultGet.tmid) {
			console.log("findReplyToMessageBmid tmid: ", resultGet.tmid);
			tmid = resultGet.tmid;
		}
	}
	catch(err) {
		return tmid;
	}
	finally {
		return tmid;
	}
}

async function findReplyToMessageTmid(tmid)
{
	let bmid;
	try {
		const resultGet = await redisClient.hGetAll(tmid);
        console.log("findReplyToMessageTmid hGetAll: ", resultGet);
		if(resultGet.bmid) {
			console.log("findReplyToMessageTmid bmid: ", resultGet.bmid);
			bmid = resultGet.bmid;
		}
	}
	catch(err) {
		return bmid;
	}
	finally {
		return bmid;
	}
}

async function configsApps(appName)
{
	return new Promise(function(resolve, reject)
	{
		let oConfigs = WHATSAPP_APPS[appName];
		resolve(oConfigs);
	});
}

function verifyJWT(req, res, next)
{
    const _AuthToken = req.headers['x-auth-token'];
	
    if (!_AuthToken) return res.status(401).json({ auth: false, message: 'No token provided.' });
    
	if(_AuthToken == JWT_TOKEN)
	{
		next();
	}
	else
	{
		res.status(500).json({ auth: false, message: 'Failed to authenticate token.' });
	}
}

async function asyncForEach(array, callback)
{
	for (let index = 0; index < array.length; index++)
	{
		await callback(array[index], index, array);
	}
}

function logsMessages(_message, _text, _json)
{
	if(_json == true)
	{
		console.log("***********************************************************");
		console.log(_message + " Json: ..:" + JSON.stringify(_text));
		console.log("***********************************************************");
	}
	else
	{
		console.log("***********************************************************");
		console.log(_message + " Text ..: " + _text);
		console.log("***********************************************************");
	}
}
