import React, { Component } from "react";
import axios from 'axios';
import { render } from "react-dom";


import Form from "react-jsonschema-form";

// Custom widgets
const widgets = {};

// Custom fields
const fields = {};

const log = (type) => console.log.bind(console, type);
const onSubmit = ({formData}) => submitData(formData);
const onChange = ({formData}) => console.log("Data changed: ",  formData);


class YodaForm extends Form {
    constructor(props) {
        super(props);
        const superOnSubmit = this.onSubmit;
        this.onSubmit = (event) => {
            event.preventDefault();

            {this.props.formContext.env == 'research' ? (
                this.props.onSubmit(this.state, { status: "submitted" })
            ) : (
                this.setState(this.state, ()=>superOnSubmit(event))
            )}
        }


    }
}

var form = document.getElementById('form');
var tokenName = form.dataset.csrf_token_name;
var tokenHash = form.dataset.csrf_token_hash;
axios.defaults.headers.common = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN' : tokenHash
};
axios.defaults.xsrfCookieName = tokenName;
axios.defaults.xsrfHeaderName = tokenHash;
var path = form.dataset.path;

axios.get("/research/metadata/data?path=" + path)
    .then(function (response) {
        // handle success
        const schema = response.data.schema;
        const uiSchema = response.data.uiSchema;
        const formData = response.data.formData;

        render((
            <YodaForm className="form form-horizontal metadata-form"
                      schema={schema}
                      idPrefix={"yoda"}
                      uiSchema={uiSchema}
                      formData={formData}
                      formContext={{env: 'research'}}
                      fields={fields}
                      widgets={widgets}
                      ArrayFieldTemplate={ArrayFieldTemplate}
                      ObjectFieldTemplate={ObjectFieldTemplate}
                      FieldTemplate={CustomFieldTemplate}
                      liveValidate={true}
                      noValidate={false}
                      noHtml5Validate={true}
                      showErrorList={true}
                      onChange={onChange}
                      onSubmit={onSubmit}
                      onError={log("errors")}>


                <div class="form-group">
                    <div class="col-sm-12">
                        <button type="submit" className="btn btn-primary">Save</button>
                        <button type="button" className="btn btn-danger delete-all-metadata-btn pull-right" data-path="">Delete all metadata</button>
                    </div>
                </div>
            </YodaForm>
        ), document.getElementById("form"));
    })
    .catch(function (error) {
        // handle error
        console.log(error);
    });

function submitData(data)
{
    var path = form.dataset.path;
    var tokenName = form.dataset.csrf_token_name;
    var tokenHash = form.dataset.csrf_token_hash;

    // Create form data
    var bodyFormData = new FormData();
    bodyFormData.set(tokenName, tokenHash);
    bodyFormData.set('formData', JSON.stringify(data));

    // Save
    axios({
        method: 'post',
        url: "/research/metadata/store?path=" + path,
        data: bodyFormData,
        config: { headers: {'Content-Type': 'multipart/form-data' }}
        })
        .then(function (response) {
            //handle success
            console.log('SUCCESS:');
            console.log(response);
            console.log(response.data);
        })
        .catch(function (error) {
            //handle error
            console.log('ERROR:');
            console.log(error);
            console.log(error.response);
        });
}

function CustomFieldTemplate(props) {
    //console.log('Field');
    //console.log(props);

    const {id, classNames, label, help, hidden, required, description, errors, rawErrors, children, displayLabel} = props;

    if (hidden || !displayLabel) {
        return children;
    }

    const hasErrors = Array.isArray(errors.props.errors) ? true : false;

    return (
        <div className={classNames}>
            <label className={'col-sm-2 control-label'}>
                <span data-toggle="tooltip" title="" data-original-title="">{label}</span>
            </label>

            {required ? (
                <span className={'fa-stack col-sm-1'}>
        <i className={'fa fa-lock safe fa-stack-1x'} aria-hidden="true" data-toggle="tooltip" title="" data-original-title="Required for the vault"></i>

                    {!hasErrors ? (
                        <i className={'fa fa-check fa-stack-1x checkmark-green-top-right'} aria-hidden="true" data-toggle="tooltip" title="" data-original-title="Filled out correctly for the vault"></i>
                    ) : (
                        null
                    )}

      </span>
            ) : (
                <span className={'fa-stack col-sm-1'}></span>
            )}
            <div className={'col-sm-9 field-wrapper'}>
                <div className={'row'}>
                    <div className={'col-sm-12'}>
                        {description}
                        {children}
                    </div>
                </div>
                {errors}
                {help}
            </div>
        </div>
    );
}

function ObjectFieldTemplate(props) {
    const { TitleField, DescriptionField } = props;

    var structure;
    if ('yoda:structure' in props.schema) {
        var structure = 'yoda-structure ' + props.schema['yoda:structure'];
    }

    return (
        <fieldset className={structure}>
            {(props.uiSchema["ui:title"] || props.title) && (
                <TitleField
                    id={`${props.idSchema.$id}__title`}
                    title={props.title || props.uiSchema["ui:title"]}
                    required={props.required}
                    formContext={props.formContext}
                />
            )}
            {props.description && (
                <DescriptionField
                    id={`${props.idSchema.$id}__description`}
                    description={props.description}
                    formContext={props.formContext}
                />
            )}
            {props.properties.map(prop => prop.content)}
        </fieldset>
    );

}

function ArrayFieldTemplate(props) {
    let array = props.items;
    let canRemove = true;
    if (array.length === 1) {
        canRemove = false;
    }
    let output = props.items.map((element, i, array) => {
        let item = props.items[i];
        if (array.length - 1 === i) {
            let btnCount = 1;
            if (canRemove) {
                btnCount = 2;
            }

            return (
                <div className="has-btn">
                    {element.children}
                    <div className={"btn-controls btn-group btn-count-" + btnCount} role="group">
                        {canRemove && <button type="button" className="clone-btn btn btn-default" onClick={item.onDropIndexClick(item.index)}>-</button>}
                        <button type="button" className="clone-btn btn btn-default" onClick={props.onAddClick}>+</button>
                    </div>
                </div>
            );
        } else {
            if (canRemove) {
                return (
                    <div className="has-btn">
                        {element.children}
                        <div className="btn-controls">
                            <button type="button" className="clone-btn btn btn-default" onClick={item.onDropIndexClick(item.index)}>-</button>
                        </div>
                    </div>
                )
            }

            return element.children;
        }
    });

    return (
        <div>
            {output}
        </div>
    );
}