import Modal from 'react-modal';
import { Map, TileLayer, Marker, FeatureGroup } from 'react-leaflet';
import L from 'leaflet';
import { EditControl } from "react-leaflet-draw";

var globalGeoBoxCounter = 0; // Additions for being able to manually add geoBoxes
var globalThis = null;

const customModalStyles = {
    content : {
        top                   : '50%',
        left                  : '50%',
        right                 : 'auto',
        bottom                : 'auto',
        marginRight           : '-50%',
        transform             : 'translate(-50%, -50%)',
        width                 : '58%',
        height                : '625px',
    }
};

class Geolocation extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            modalIsOpen: false,
            ...props.formData
        };

        this.openModal = this.openModal.bind(this);
        this.closeModal = this.closeModal.bind(this);
        this.afterOpenModal = this.afterOpenModal.bind(this);
        this.drawCreated = this.drawCreated.bind(this);
        this.drawEdited = this.drawEdited.bind(this);
        this.drawDeleted = this.drawDeleted.bind(this);
        this.drawStop = this.drawStop.bind(this);
        this.setFormData = this.setFormData.bind(this);
        this.geoBoxID = globalGeoBoxCounter;
        globalGeoBoxCounter++;
    }

    openModal(e) {
        e.preventDefault();

        globalThis = this; // @todo: get rid of this dirty trick

        this.setState({modalIsOpen: true});
    }

    closeModal(e) {
        e.preventDefault();
        this.setState({modalIsOpen: false});
    }

    afterOpenModal(e) {
        const {northBoundLatitude, westBoundLongitude, southBoundLatitude, eastBoundLongitude} = this.state;
        let map = this.refs.map.leafletElement;
        if (typeof northBoundLatitude !== 'undefined' &&
            typeof westBoundLongitude !== 'undefined' &&
            typeof southBoundLatitude !== 'undefined' &&
            typeof eastBoundLongitude !== 'undefined'
        ) {
            let bounds = [
                [northBoundLatitude,       westBoundLongitude],
                [southBoundLatitude + 0.1, eastBoundLongitude + 0.1]
            ];

            // Coordinates are a point.
            if (northBoundLatitude == southBoundLatitude && westBoundLongitude == eastBoundLongitude) {
                var latlng = L.latLng(northBoundLatitude, westBoundLongitude);
                L.marker(latlng).addTo(map);
            } else {
                L.rectangle(bounds).addTo(map);
            }
            map.fitBounds(bounds, {'padding': [150, 150]});
        }

        this.fillCoordinateInputs(northBoundLatitude, westBoundLongitude, southBoundLatitude, eastBoundLongitude);

        $('.geoInputCoords').on('input propertychange paste', function() {
            var boxID = $(this).attr("boxID");

            // Remove earlier markers and rectangle(s)
            map.eachLayer(function (layer) {
                if (layer instanceof L.Marker || layer instanceof L.Rectangle) {
                    map.removeLayer(layer);
                }
            });

            // only make persistent when correct coordinates are added by user
            var lat0 = Number($(".geoLat0[boxID='" + boxID + "']").val()),
                lng0 = Number($(".geoLng0[boxID='" + boxID + "']").val()),
                lat1 = Number($(".geoLat1[boxID='" + boxID + "']").val()),
                lng1 = Number($(".geoLng1[boxID='" + boxID + "']").val()),
                alertText = '';

            // Validation of coordinates - resetten als dialog wordt heropend
            if(!$.isNumeric( lng0 )){
                alertText += ', WEST';
            }
            if(!$.isNumeric( lat0 )){
                alertText = ', NORTH';
            }
            if(!$.isNumeric( lng1 )){
                alertText += ', EAST';
            }
            if(!$.isNumeric( lat1 )){
                alertText += ', SOUTH';
            }

            if (alertText) {
                $('.geoAlert[boxID="' + boxID + '"]').html('Invalid coordinates: ' + alertText.substring(2));
            } else {
                $('.geoAlert[boxID="' + boxID + '"]').html(''); // reset the alert box -> no alert required
                let bounds = [[lat0, lng0], [lat1 + 0.1, lng1 + 0.1]];

                // Coordinates are a point.
                if (lat0 == lat1 && lng0 == lng1) {
                    var latlng = L.latLng(lat0, lng0);
                    L.marker(latlng).addTo(map);
                } else {
                    L.rectangle(bounds).addTo(map);
                }
                map.fitBounds(bounds, {'padding': [150, 150]});

                globalThis.setFormData('northBoundLatitude', lat0);
                globalThis.setFormData('westBoundLongitude', lng0);
                globalThis.setFormData('southBoundLatitude', lat1);
                globalThis.setFormData('eastBoundLongitude', lng1);
            }
       });
    }

    fillCoordinateInputs(northBoundLatitude, westBoundLongitude, southBoundLatitude, eastBoundLongitude) {
        $('.geoLat0').val(northBoundLatitude);
        $('.geoLng0').val(westBoundLongitude);
        $('.geoLat1').val(southBoundLatitude);
        $('.geoLng1').val(eastBoundLongitude);
    }

    drawCreated(e) {
        let layer = e.layer;

        if (layer instanceof L.Marker) {
            this.setFormData('northBoundLatitude', layer.getLatLng().lat);
            this.setFormData('westBoundLongitude', layer.getLatLng().lng);
            this.setFormData('southBoundLatitude', layer.getLatLng().lat);
            this.setFormData('eastBoundLongitude', layer.getLatLng().lng);

            this.fillCoordinateInputs(
                layer.getLatLng().lat, layer.getLatLng().lng,
                layer.getLatLng().lat, layer.getLatLng().lng
            );
        } else if (layer instanceof L.Rectangle)  {
            this.setFormData('northBoundLatitude', layer.getLatLngs()[0][2].lat);
            this.setFormData('westBoundLongitude', layer.getLatLngs()[0][2].lng);
            this.setFormData('southBoundLatitude', layer.getLatLngs()[0][0].lat);
            this.setFormData('eastBoundLongitude', layer.getLatLngs()[0][0].lng);

            this.fillCoordinateInputs(
                layer.getLatLngs()[0][2].lat, layer.getLatLngs()[0][2].lng,
                layer.getLatLngs()[0][0].lat, layer.getLatLngs()[0][0].lng
            );
        }
    }

    drawEdited(e) {
        e.layers.eachLayer( (layer) => {
            if (layer instanceof L.Marker) {
                this.setFormData('northBoundLatitude', layer.getLatLng().lat);
                this.setFormData('westBoundLongitude', layer.getLatLng().lng);
                this.setFormData('southBoundLatitude', layer.getLatLng().lat);
                this.setFormData('eastBoundLongitude', layer.getLatLng().lng);

                this.fillCoordinateInputs(
                    layer.getLatLng().lat, layer.getLatLng().lng,
                    layer.getLatLng().lat, layer.getLatLng().lng
                );
            } else if (layer instanceof L.Rectangle)  {
                this.setFormData('northBoundLatitude', layer.getLatLngs()[0][2].lat);
                this.setFormData('westBoundLongitude', layer.getLatLngs()[0][2].lng);
                this.setFormData('southBoundLatitude', layer.getLatLngs()[0][0].lat);
                this.setFormData('eastBoundLongitude', layer.getLatLngs()[0][0].lng);

                this.fillCoordinateInputs(
                    layer.getLatLngs()[0][2].lat, layer.getLatLngs()[0][2].lng,
                    layer.getLatLngs()[0][0].lat, layer.getLatLngs()[0][0].lng
                );
            }
        });
    }

    drawDeleted(e) {
        this.setFormData('northBoundLatitude', undefined);
        this.setFormData('westBoundLongitude', undefined);
        this.setFormData('southBoundLatitude', undefined);
        this.setFormData('eastBoundLongitude', undefined);

        this.fillCoordinateInputs("", "", "", "");
    }

    drawStop(e) {
        let map = this.refs.map.leafletElement;
        map.eachLayer(function (layer) {
            if (layer instanceof L.Marker || layer instanceof L.Rectangle) {
                map.removeLayer(layer);
            }
        });
    }

    setFormData(fieldName, fieldValue) {
        this.setState({
            [fieldName]: fieldValue
        }, () => this.props.onChange(this.state));
    }

    render() {
        const {northBoundLatitude, westBoundLongitude, southBoundLatitude, eastBoundLongitude} = this.state;
        return (
                <div class={'form-group geoDiv' + this.geoBoxID}>
                  <label class="col-sm-2 control-label">
                    <span>Geolocation</span>
                  </label>
                  <span class="fa-stack col-sm-1"></span>
                  <div class="col-sm-9">
                    <button class='btn' onClick={(e) => {this.openModal(e); }}>Open Map</button>&nbsp;
                    WN: {westBoundLongitude}, {northBoundLatitude} ES: {eastBoundLongitude}, {southBoundLatitude}
                  </div>

                <Modal
                    isOpen={this.state.modalIsOpen}
                    onAfterOpen={this.afterOpenModal}
                    onRequestClose={this.closeModal}
                    style={customModalStyles}
                    ariaHideApp={false}
                >
                    <Map ref='map' center={[48.760, 13.275]} zoom={4} animate={false}>
                        <TileLayer
                            attribution='&amp;copy <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
                            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                        />
                        <FeatureGroup>
                            <EditControl
                                position='topright'
                                onCreated={this.drawCreated}
                                onEdited={this.drawEdited}
                                onDeleted={this.drawDeleted}
                                onDrawStart={this.drawStop}
                                draw={{
                                    circle: false,
                                    polygon: false,
                                    marker: true,
                                    circlemarker: false,
                                    polyline: false
                                }}
                            />
                        </FeatureGroup>
                    </Map>

                    <div class='row'>
                        <div class='col-sm-11'>
                            <label>West:</label> <input type='text' class='geoInputCoords geoLng0' boxID={this.geoBoxID}></input>
                            <label>North:</label> <input type='text' class='geoInputCoords geoLat0' boxID={this.geoBoxID}></input>
                            <label>East:</label> <input type='text' class='geoInputCoords geoLng1' boxID={this.geoBoxID}></input>
                            <label>South:</label> <input type='text' class='geoInputCoords geoLat1' boxID={this.geoBoxID}></input>
                        </div>
                        <div class='col-sm-1'>
                            <button class='btn' onClick={(e) => {this.closeModal(e); }}>Close</button>
                        </div>
                    </div>
                    <div class='geoAlert' boxID={this.geoBoxID}></div>
                </Modal>
            </div>
        );
    }
}